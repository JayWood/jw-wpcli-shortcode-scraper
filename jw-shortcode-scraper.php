<?php

namespace JW\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Produces shortcode reports for you or clients ( even unregistered shortcodes ).
	 */
	class Shortcode_Scraper extends WP_CLI_Command {

		private $args       = [];
		private $site       = false;
		private $assoc_args = [];
		private $progress_bar;

		/**
		 * Scrapes post content and provides a list of shortcodes that are in use.
		 *
		 * # OPTIONS
		 *
		 * [--export]
		 * : Exports the results to a CSV file.
		 *
		 * [--site=<slug>]
		 * : The site to query
		 */
		public function scrape( $args, $assoc_args ) {
			$this->args       = $args;
			$this->assoc_args = $assoc_args;

			// Rather or not to export the results.
			$export = isset( $assoc_args['export'] );

			$this->site = isset( $assoc_args['site'] ) ? $assoc_args['site'] : false;
			if ( $this->site ) {
				$blog_details = get_blog_details( $this->site );
				if ( ! $blog_details ) {
					WP_CLI::error( sprintf( 'Getting blog details for %s failed', $this->site ) );
				}
				switch_to_blog( $blog_details->blog_id );
			}

			// Use these to store some arrays.
			$results = [];

			// Process the post results.
			$multiplier = 0;

			while ( $posts = $this->query_posts( $multiplier ) ) {

				$this->progress_bar( count( $posts ), 'Post Objects', 'Processing' );

				$this->process_posts( $posts, $results );

				$this->progress_bar( 'finish' );

				$multiplier ++;
			};

			WP_CLI\Utils\format_items( 'table', $results, array(
				'post_id',
				'post_name',
				'shortcode',
				'parameters_raw',
			) );

			if ( $export ) {
				try {
					$date = current_time( 'Y-m-d' );
					if ( $this->site ) {
						$date = $this->site . '-' . $date;
					}
					$file = fopen( dirname( __FILE__ ) . '/shortcode-scrape-' . $date . '.csv', 'w' );

					fputcsv( $file, array_keys( $results[0] ) );

					foreach ( $results as $result ) {
						fputcsv( $file, $result );
					}
					fclose( $file );
				} catch ( \Exception $e ) {
					WP_CLI::error( 'Error running export method: ' . $e->getMessage() );
				}
			}

			// Display the table.

			// output results
		}

		/**
		 * A simple SQL loopable query for pages and posts.
		 *
		 * @param int $multiplier The multiplier for batch processing
		 *
		 * @return false|array Array of objects on success, null otherwise.
		 */
		private function query_posts( $multiplier = 0 ) {
			global $wpdb;

			$sql_query = "
			SELECT ID,post_name,post_content
			FROM {$wpdb->posts}
			WHERE post_status = %s
			AND post_type IN ( %s, %s )
			LIMIT 100 OFFSET %d
			";

			$sql_query  = $wpdb->prepare( $sql_query, 'publish', 'post', 'page', ( $multiplier * 100 ) );  // @codingStandardsIgnoreLine Code is provided above.
			$result_set = $wpdb->get_results( $sql_query );

			return count( $result_set ) > 0 ? $result_set : false;  // @codingStandardsIgnoreLine Code is sanitized above.
		}

		/**
		 * Processes posts queried by the database.
		 *
		 * @param array $posts Array of post objects
		 * @param array $results Passing by reference, an array of results to append to/modify.
		 *
		 * @return void
		 */
		private function process_posts( $posts, &$results ) {

			$regex = '/\[([a-zA-Z0-9_-]+) ?([^\]]+)?/';

			foreach ( $posts as $post_data ) {

				$this->progress_bar( 'tick' );

				// Skip if we do not have post content.
				if ( empty( $post_data->post_content ) ) {
					continue;
				}

				preg_match_all( $regex, $post_data->post_content, $shortcodes );
				$shortcodes = array_filter( $shortcodes );

				if ( empty( $shortcodes ) || empty( $shortcodes[1] ) ) {
					continue;
				}

				$shortcode_strings    = $shortcodes[1];
				$shortcode_parameters = empty( $shortcodes[2] ) ? array() : $shortcodes[2];

				// Walk over each parameter set, removing items which are not shortcodes.
				$fakes = [];
				foreach ( $shortcode_parameters as $key => $value ) {
					if ( empty( $value ) ) {
						$shortcode_parameters[ $key ] = [];
						continue;
					}

					if ( false === strstr( $value, '=' ) ) {
						// This may not be a shortcode, skip it.
						$fakes[] = $key;
						continue;
					}

					$shortcode_parameters[ $key ] = shortcode_parse_atts( $value );
				}

				// Remove fakes.
				foreach ( $fakes as $fake ) {
					unset( $shortcode_strings[ $fake ], $shortcode_parameters[ $fake ] );
				}

				// Combine them
				$codes = [];
				foreach ( $shortcode_strings as $key => $shortcode_string ) {
					$codes[] = [
						'shortcode' => $shortcode_string,
						'values'    => $shortcode_parameters[ $key ],
					];
				}

				// Loop over and format codes for the results array now.
				foreach ( $codes as $code ) {
					$results[] = array(
						'post_id'        => $post_data->ID,
						'shortcode'      => $code['shortcode'],
						'post_name'      => $post_data->post_name,
						'parameters'     => $this->format_params( $code['values'] ),
						'parameters_raw' => json_encode( $code['values'] ),
					);
				}
			} // End foreach().
		}

		/**
		 * Formats parameters into a readable output for the CSV.
		 *
		 * @param string $parameters
		 *
		 * @return string
		 */
		private function format_params( $parameters = '' ) {
			if ( empty( $parameters ) || ! is_array( $parameters ) ) {
				return '';
			}

			$out = [];
			foreach ( $parameters as $k => $v ) {
				if ( is_int( $k ) ) {
					$out[] = $v;
				} else {
					$out[] = sprintf( '%1$s: %2$s', $k, $v );
				}
			}

			return implode( "\r\n", $out );
		}

		/**
		 * Wrapper function for WP_CLI Progress bar
		 *
		 * @param int|string $param If integer, start progress bar, if string, should be tick or finish.
		 * @param string $object_type Type of object being traversed
		 * @param string $action Action being performed
		 *
		 * @return bool|object False on failure, WP_CLI progress bar object otherwise.
		 */
		private function progress_bar( $param, $object_type = '', $action = 'Migrating' ) {

			if ( $param && is_numeric( $param ) ) {
				$this->progress_bar = \WP_CLI\Utils\make_progress_bar( "$action $param $object_type.", $param );
			} elseif ( ( $this->progress_bar && 'tick' == $param ) && method_exists( $this->progress_bar, 'tick' ) ) {
				$this->progress_bar->tick();
			} elseif ( ( $this->progress_bar && 'finish' == $param ) && method_exists( $this->progress_bar, 'finish' ) ) {
				$this->progress_bar->finish();
			}

			return $this->progress_bar;
		}
	}

	WP_CLI::add_command( 'jw-shortcode-scraper', __NAMESPACE__ . '\Shortcode_Scraper' );
}
