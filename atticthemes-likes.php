<?php
/*
Plugin Name: AtticThemes: Likes
Plugin URI: http://atticthemes.com
Description: A simple plugin to add likes to your posts.
Version: 1.0.1
Author: atticthemes
Author URI: http://atticthemes.com
Requires: 4.0.0
Tested: 4.5.3
Updated: 2016-06-18
Added: 2016-06-18
*/


if( !class_exists('AtticThemes_Likes') ) {
	class AtticThemes_Likes {

		/**
		 * Plugin version
		 */
		const VERSION = '1.0.1';

		/**
		 * Defines if the plugin is in development stage or not
		 */
		const IS_DEV = false;

		/**
		 * suffix to append to the filename of resources
		 */
		const MIN_SUFFIX = '.min';


		/**
		 * Current file
		 */
		const FILE = __FILE__;



		/**
		 * Holds the supported post types added with add_theme_support();
		 */
		public static $post_types;

		/**
		 * Counter object for likes
		 */
		public static $counter;

		/**
		 * Holds the data about theme support
		 */
		public static $support;


		public static function init() {
			/**
			 * define $post_types as array
			 */
			self::$post_types = array();

			/**
			 * Add post type support
			 */
			self::$support = get_theme_support( 'atlp_likes' );

			if( !self::$support ) return;

			if( isset(self::$support[0]) ) {
				if(	is_array(self::$support[0]) ) {
					self::$post_types = array_merge( self::$post_types, self::$support[0] );
				} else {
					self::$post_types = array( self::$support[0] );
				}
			}

			if( !class_exists('AtticThemes_Counter') ) return;

			/**
			 * Add a counter for likes
			 */
			self::$counter = new AtticThemes_Counter( array(
					'name' => 'post_likes',
					'loggedin' => true,
				)
			);

			//error_log(print_r(self::$counter, true));

			/**
			 * add the likes into the content
			 */
			if( isset(self::$support[1]) && self::$support[1] ) {
				add_filter( 'the_content', array(__CLASS__, 'the_content_filter') );
			}


			/**
			 * add scripts and styles
			 */
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_resources' ) );

			/**
			 * hook to post save action to add default meta values
			 */
			add_action( 'save_post', array( __CLASS__, 'reset_count' ) );



			/**
			 * add a new column in posts list page
			 */
			add_filter( 'manage_posts_columns' , array( __CLASS__, 'add_likes_column' ) );

			/**
			 * make the column sortable
			 */
			add_filter( 'manage_edit-post_sortable_columns' , array( __CLASS__, 'make_sortable_likes_column' ) );

			/**
			 * display the new column
			 */
			add_action( 'manage_posts_custom_column' , array( __CLASS__, 'display_likes_column' ), 10, 2 );

			/**
			 * Hook into the load-edit.php to make sure we are on the edit.php page
			 */
			add_action( 'load-edit.php', array( __CLASS__, 'edit_post_load' ) );
		}


		public static function edit_post_load() {
			add_filter( 'request', array( __CLASS__, 'sort_posts_by_likes' ) );
		}

		public static function make_sortable_likes_column( $columns ) {
			$columns['atlp-likes'] = 'atlp-likes';
			return $columns;
		}

		public static function add_likes_column( $columns ) {
			$new_columns = array();

			foreach ($columns as $key => $value) {
				$new_columns[$key] = $value;

				if( $key === 'tags' ) {
					$new_columns['atlp-likes']  = '<span title="'.__('Likes','atlp').'" class="dashicons dashicons-heart"></span>';
					$new_columns['atlp-likes'] .= '<span class="screen-reader-text">'.__('Likes','atlp').'</span>';
					
				}
			}

			return $new_columns;
		}

		public static function display_likes_column( $column, $post_id ) {
			if ( $column == 'atlp-likes' ) {
				$count = self::get_count( $post_id );
				$likes = $count ? $count : 0;
				$title = sprintf( _n( '%s Like', '%s Likes', $likes, 'atlp' ), number_format($likes) );
				echo '<span title="'. $title .'">'. self::shorten_large_number( absint($likes) ) .'</span>';
			}
		}

		public static function shorten_large_number( $size ) {
			$mod = 1000;
			$units = array( '', 'K', 'M', 'B' );

			for ($i = 0; $size > $mod; $i++) {
				$size /= $mod;
			}

			$splits = explode( '.', $size );
			if( isset($splits[1]) ) {
				$splits[1] = substr($splits[1], 0, 1);
			}

			return implode('.', $splits) . $units[$i];
		}

		public static function sort_posts_by_likes( $vars ) {
			/**
			* Check if we are viewing "post" post type.
			*/
			if ( isset($vars['post_type']) && $vars['post_type'] === 'post' ) {
				/**
				* Check if 'orderby' is set to 'atlp-likes'.
				*/
				if ( isset($vars['orderby']) && $vars['orderby'] === 'atlp-likes' ) {

					/**
					* Merge the query vars with our custom variables.
					*/
					$vars = array_merge( $vars, array(
							'meta_key' => 'atlp_post_likes',
							'orderby' => 'meta_value_num'
						)
					);
				}
			}

			return $vars;
		}


		

		public static function the_content_filter( $content ) {
			/**
			 * Return the $content unmodified if the post type of current post is not supported
			 */
			//error_log(print_r(self::$post_types, true));
			if( !in_array(get_post_type(), self::$post_types) ) return $content;

			$content .= self::get();
			return $content;
		}


		/**
		 * get the murkup
		 */
		public static function get() {
			if( !self::$counter || !in_array(get_post_type(), self::$post_types) )  return;

			/**
			 * get the number of likes
			 */
			$count = intval( self::get_count( get_the_ID() ) );

			$likes  = '<span class="atlp-count"> ';
			$likes .= self::shorten_large_number( absint($count ? $count : 0) );
			$likes .= '</span>';

			$classes = array( 'atlp-likes' );
			if( self::$counter->is_returning(get_the_ID()) ) {
				$classes[] = 'atlp-liked';
			}

			$title = sprintf( _n( '%s Like', '%s Likes', $count, 'atlp' ), number_format($count) );

			$output = '<span title="'. $title .'" class="'. implode(' ', $classes) .'" data-post-id="'. get_the_ID() .'" data-single="'. __('%s Like', 'atlp') .'" data-plural="'. __('%s Likes', 'atlp') .'">';
			$output .= '<span class="label">';
			$output .= sprintf( _n( '%s Like', '%s Likes', $count, 'atlp' ), $likes );
			$output .= '</span>';
			$output .= '</span>';

			return apply_filters('atlp_likes', $output, get_the_ID(), $count);
		}


		/**
		 * add styles and scripts
		 */
		public static function add_resources() {
			wp_register_script(
				'atlp-script',
				plugins_url( 'resources/javascript/script'. self::min() .'.js', self::FILE ),
				array('jquery'),
				self::VERSION
			);
			/**
			 * add main style
			 */
			wp_enqueue_script( 'atlp-script' );


			/**
			 * make some data available for JavaScript
			 */
			wp_localize_script( 'atlp-script', 'atlp_data', json_encode(
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'likes' => self::$counter,
						'post_id' => get_the_ID(),
						'is_logedin' => is_user_logged_in() ? true : null,
						'is_single' => is_single() ? true : null
					)					
				)
			);
			//

			wp_register_style(
				'atlp-style',
				plugins_url( 'resources/css/style'. self::min() .'.css', self::FILE ),
				array(),
				self::VERSION
			);
			/**
			 * add main style
			 */
			wp_enqueue_style( 'atlp-style' );
		}

		/**
		 * adds meta keys with default values to all supported posts
		 */
		public static function add_meta_keys() {
			if( !self::$counter ) return;

			/**
			 * get all posts of the supported post types
			 */
			$all_posts = new WP_Query( array(
					'post_type' => self::$post_types,
					'posts_per_page' => -1
				)
			);

			/**
			 * add the key with value 0 if the key does not exist
			 */
			while( $all_posts->have_posts() ) { $all_posts->the_post();
				$count = self::$counter->get_count($all_posts->post->ID);

				if( !isset($count) ) {
					self::set_count( $all_posts->post->ID, 0 );
				}
			}

			/**
			 * reset post data
			 */
			wp_reset_postdata();
		}

		/**
		 * gets the number of likes
		 */
		public static function get_count( $post_id ) {
			if( !self::$counter ) return;
			$count = self::$counter->get_count($post_id);
			return isset($count) ? $count : 0;
		}

		/**
		 * sets the number of likes
		 */
		public static function set_count( $post_id, $count ) {
			if( !self::$counter ) return;
			return self::$counter->set_count( $post_id, intval($count) );
		}

		/**
		 * set counter to zero on post save if there is no likes meta yet.
		 */
		public static function reset_count( $post_id ) {
			/**
			 *
			 */
			if( wp_is_post_revision( $post_id) || wp_is_post_autosave($post_id) ) return;

			$count = self::$counter->get_count($post_id);

			//error_log(var_export($count . ' : ' . $post_id, true));

			if( !isset($count) ) {
				self::set_count( $post_id, 0 );
			}
		}

		/**
		 * get the liked posts ids
		 */
		public static function get_liked() {
			if( !self::$counter ) return;

			return self::$counter->get_cookies();
		}


		/**
		 * checks if "Duplicate Post" plugin is being used to duplicate the post
		 */
		public static function is_duiplicating() {
			if( isset($_GET['action']) && $_GET['action'] === 'duplicate_post_save_as_new_post' ) {
				return true;
			}
			return false;
		}


		/**
		 * Returns the min suffix if not in development
		 */
		public static function min() {
			if( self::IS_DEV ) {
				return '';
			} else {
				return self::MIN_SUFFIX;
			}
		}



		/**
		 * add theme support for blog posts
		 */
		public static function add_theme_support() {
			add_theme_support( 'atlp_likes', array('post'), true );
		}

		/**
		 * remove theme support for blog posts
		 */
		public static function remove_theme_support() {
			remove_theme_support( 'atlp_likes' );
		}


		/**
		 * Function to be called upon activation of the plugin
		 */
		public static function activate() {
			/**
			 * Init when activated so the counter is available
			 */
			self::init();

			/**
			 * add meta keys with default values to all supported posts
			 */
			self::add_meta_keys();
		}

	}

	/**
	 * Include the counter class
	 */
	require_once( plugin_dir_path( __FILE__ ) . 'includes/counter.php' );


	/**
	 * hook into Blog Extender's init action
	 */
	add_action( 'init', array('AtticThemes_Likes', 'init') );

	/**
	 * add support for post likes by default
	 */
	add_action( 'after_setup_theme', array('AtticThemes_Likes', 'add_theme_support') );


	/**
	 * plugin activation hook
	 */
	register_activation_hook( __FILE__, array('AtticThemes_Likes', 'activate') );
}