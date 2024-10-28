<?php

if( !class_exists('AtticThemes_Counter') ) {

	class AtticThemes_Counter {
		public $settings;
		private $nonce_string;
		private $cookie;
		private $meta_namespace;

		function __construct( $settings ) {
			if( !isset($settings['name']) ) return;

			$defaults = array(
				'multiple' => false,
				'loggedin' => false,
			);

			$this->settings = array_merge( $defaults, $settings );
			$this->nonce_string = 'atlp-' . $this->settings['name'] . '-Nonce-Ajax';
			$this->nonce = wp_create_nonce( $this->nonce_string );
			$this->cookie = 'wordpress_atlp_' . $this->settings['name'];
			$this->meta_namespace = 'atlp_' . $this->settings['name'];
			$this->add_action = 'atlp_add_' . $this->settings['name'];
			$this->remove_action = 'atlp_remove_' . $this->settings['name'];

			/* ajax actions */

			/* add */
			add_action( 'wp_ajax_nopriv_' . $this->add_action, array( $this, 'add_count_ajax') );
			add_action( 'wp_ajax_' . $this->add_action, array( $this, 'add_count_ajax') );
			/* remove */
			add_action( 'wp_ajax_nopriv_' . $this->remove_action, array( $this, 'remove_count_ajax') );
			add_action( 'wp_ajax_' . $this->remove_action, array( $this, 'remove_count_ajax') );
		}


		public function get_count( $post_id ) {
			$count = get_post_meta($post_id, $this->meta_namespace, true);
			//error_log(var_export($count . ' : ' . $post_id, true));

			if( $count === false || $count === '' ) {
				return null;
			} elseif( $count === 0 || $count === '0' ) {
				return 0;
			}

			return intval($count);
		}


		public function add_count_ajax() {
			if ( !isset($_REQUEST['nonce']) || (isset($_REQUEST['nonce']) && !wp_verify_nonce( $_REQUEST['nonce'], $this->nonce_string)) ) {
				exit('Do not even try!');
			}

			if( isset($_REQUEST['post_id']) && !empty($_REQUEST['post_id']) ) {
				$post_id = $_REQUEST['post_id'];
				$count = $this->add_count( $post_id );

				if( isset($count) ) {
					echo json_encode( array('message' => 'success', 'counter' => $count) );
				} else {
					echo json_encode( array('message' => 'error') );
				}
			}
			die;
		}

		public function remove_count_ajax() {
			if ( !isset($_REQUEST['nonce']) || (isset($_REQUEST['nonce']) && !wp_verify_nonce( $_REQUEST['nonce'], $this->nonce_string)) ) {
				exit('Do not even try!');
			}

			if( isset($_REQUEST['post_id']) && !empty($_REQUEST['post_id']) ) {
				$post_id = $_REQUEST['post_id'];
				$count = $this->remove_count( $post_id );

				if( isset($count) ) {
					echo json_encode( array('message' => 'success', 'counter' => $count) );
				} else {
					echo json_encode( array('message' => 'error') );
				}
			}
			die;
		}

		public function set_count( $post_id, $count = 0 ) {
			update_post_meta($post_id, $this->meta_namespace, intval($count) );
		}

		public function add_count( $post_id = null ) {
			if( is_user_logged_in() && !$this->settings['loggedin'] ) return null;

			if( $this->settings['multiple'] ) {
				$this->unset_cookie( $post_id );
			}

			$count = get_post_meta($post_id, $this->meta_namespace, true);

			//error_log(var_export($count, true));

			if ( $this->is_returning(intval($post_id)) ) {
				return intval($count);
			}
			
			if( $count ) {
				$count = $count + 1;
			} else {
				$count = 1;
			}

			update_post_meta($post_id, $this->meta_namespace, $count);

			/* update the total */
			$total = get_option( $this->meta_namespace, 0 );
			update_option( $this->meta_namespace, $total + 1 );

			if( !$this->settings['multiple'] ) {
				$this->set_cookie( $post_id );
			}
			
			return intval($count);
		}

		public function remove_count( $post_id = null ) {
			if( is_user_logged_in() && !$this->settings['loggedin'] ) return null;

			if( $this->settings['multiple'] ) {
				$this->unset_cookie( $post_id );
			}

			$count = get_post_meta($post_id, $this->meta_namespace, true);
			if ( !$this->is_returning(intval($post_id)) ) {
				return intval($count);
			}
			
			if( intval($count) > 0 ) {
				$count = $count - 1;
			} else {
				$count = 0;
			}

			update_post_meta($post_id, $this->meta_namespace, $count);

			/* update the total */
			$total = get_option( $this->meta_namespace, 0 );
			if( intval($total) > 0 ) {
				update_option( $this->meta_namespace, $total - 1 );
			}
			

			$this->unset_cookie( $post_id );

			if( !$this->settings['multiple'] ) {
				$this->set_cookie( $post_id );
			}
			
			return intval($count);
		}



		protected function set_cookie( $post_id = null ) {
			if ( !is_user_logged_in() || $this->settings['loggedin'] ) {
				$posts_list = isset($_COOKIE[$this->cookie]) ? $_COOKIE[$this->cookie] : '';
				$posts = !empty($posts_list) ? explode( ',', $posts_list ) : array();

				if( !in_array($post_id, $posts) ) {
					$posts[] = $post_id;
					setcookie( $this->cookie, implode(',',$posts), time()+3600*24*365, COOKIEPATH, COOKIE_DOMAIN, false, true );
				}
			}
		}

		protected function unset_cookie( $post_id = null ) {
			if ( !is_user_logged_in() || $this->settings['loggedin'] ) {
				$posts_list = isset($_COOKIE[$this->cookie]) ? $_COOKIE[$this->cookie] : '';
				$posts = !empty($posts_list) ? explode( ',', $posts_list ) : array();

				if( in_array($post_id, $posts) ) {
					$posts = array_diff( $posts, array($post_id) );
					setcookie( $this->cookie, implode(',',$posts), time()+3600*24*365, COOKIEPATH, COOKIE_DOMAIN, false, true );
				}
			}
		}

		
		public function get_cookies() {
			$posts_list = isset($_COOKIE[$this->cookie]) ? $_COOKIE[$this->cookie] : '';
			return !empty($posts_list) ? explode( ',', $posts_list ) : array();
		}






		public function is_returning( $post_id = null ) {
			$posts_list = isset($_COOKIE[$this->cookie]) ? $_COOKIE[$this->cookie] : '';
			$posts = !empty($posts_list) ? explode( ',', $posts_list ) : array();

			if( !empty($posts) && in_array($post_id, $posts) ) {
				return true;
			} else {
				return false;
			}
		}

	} //END class

}