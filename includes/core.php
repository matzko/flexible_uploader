<?php

class WP_Flexible_Uploader_Control {

	public $endpoint;
	public $model;
	public $view;
	
	public function __construct()
	{
		$this->_set_model();
		$this->_set_view();
		$this->_set_endpoint_listener();

		$this->endpoint->model = &$this->model;
		$this->endpoint->view = &$this->view;
		
		$this->view->model = &$this->model;

		$this->enqueue_scripts();
	}

	protected function _set_endpoint_listener()
	{
		$this->endpoint = new WP_Flexible_Uploader_Endpoints;
		add_action( 'init', array(&$this->endpoint, 'listen_for_form_requests' ) );
		add_action( 'init', array(&$this->endpoint, 'listen_for_submissions' ) );
	}

	protected function _set_model()
	{
		$this->model = new WP_Flexible_Uploader_Model; 	
	}

	protected function _set_view()
	{
		$this->view = new WP_Flexible_Uploader_View; 	
	}

	public function enqueue_scripts()
	{
		add_action( 'wp_head', array( &$this->view, 'print_header' ) );
		add_action( 'admin_head', array( &$this->view, 'print_header' ) );

		$scripts = array(
			'plupload-gears' => array(
				'gears_init.js',
				null,
			),
			
			'plupload-browserplus' => array(
				'plupload.browserplus.min.js',
				array(
					'plupload',
				),
			),

/*
			'plupload-flash' => array(
				'plupload.flash.min.js',
				array(
					'plupload',
				),
			),
			*/

			'plupload' => array(
				'plupload.full.min.js',
			//	'plupload.js',
				array(
					'plupload-gears',
				),
			),

/*
			'plupload-queue' => array(
				'jquery.plupload.queue.min.js',
				array(
					'plupload',
				),
			),
			*/

			'flexible-uploader' => array(
				'flexible-uploader.js',
				array(
					'plupload',
				),
			),
		);
		
		foreach( $scripts as $slug => $data ) {
			wp_enqueue_script(
				$slug,
				plugins_url(
					'client-files/js/' . array_shift( $data ),
					dirname( __FILE__ )
				),
				array_shift( $data ),
				'1.0'
			);
		}

	}
}

class WP_Flexible_Uploader_Model {
	protected $_basedir = '';
	protected $_baseurl = '';
	protected $_path = '';
	protected $_url = '';
	protected $_subdir = '';
	
	public $browse_button_id = 'wp-flexible-browse-button';
	public $file_name_id = 'wp-flexible-uploader-name';
	public $form_id = 'wp-flexible-uploader-form';
	public $progress_bar_id = 'wp-flexible-uploader-progress-bar-id';
	public $progress_bar_wrap = 'wp-flexible-uploader-progress-bar-wrap';
	public $request_variable_id = 'wp-flexible-uploader';
	public $upload_wrap_id = 'wp-flexible-upload-wrap';
	
	public function __construct()
	{
		$date_info = apply_filters( 'wp_flexible_uploader_date_structure', date( 'Y/m' ) );

		$upload_dir_info = wp_upload_dir( $date_info );
		if ( empty( $upload_dir_info[ 'error' ] ) ) {
			$this->_basedir = $upload_dir_info[ 'basedir' ];
			$this->_baseurl = $upload_dir_info[ 'baseurl' ];
			$this->_path = $upload_dir_info[ 'path' ];
			$this->_subdir = $upload_dir_info[ 'subdir' ];
			$this->_url = $upload_dir_info[ 'url' ];
		}
	}

	public function user_can_upload( $user_id = 0 )
	{
		$user_id = empty( $user_id ) ? get_current_user_id() : (int) $user_id;
		
		$cap = 'manage_options';

		$user = new WP_User( $user_id );
		
		return call_user_func_array( array( $user, 'has_cap' ), array( $cap ) );
	}

	public function user_can_view_form( $user_id = 0 )
	{
		$user_id = empty( $user_id ) ? get_current_user_id() : (int) $user_id;
		
		$cap = 'manage_options';

		$user = new WP_User( $user_id );
		
		return call_user_func_array( array( $user, 'has_cap' ), array( $cap ) );
	}

	public function get_chunk_size( $type = 'mb' )
	{
		return 1;
	}

	public function get_max_upload_size( $type = 'mb' )
	{
		$u_bytes = $this->_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$p_bytes = $this->_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		// @todo figure out a workaround for upload size limit filter, as some callbacks available only in admin
		// $bytes = apply_filters( 'upload_size_limit', min($u_bytes, $p_bytes), $u_bytes, $p_bytes );
		$bytes = min($u_bytes, $p_bytes);
		$mb = floor( $bytes / ( 1024 * 1024 ) );
		return $mb;
	}
	
	protected function _convert_hr_to_bytes( $size ) {
		$size = strtolower($size);
		$bytes = (int) $size;
		if ( strpos($size, 'k') !== false )
			$bytes = intval($size) * 1024;
		elseif ( strpos($size, 'm') !== false )
			$bytes = intval($size) * 1024 * 1024;
		elseif ( strpos($size, 'g') !== false )
			$bytes = intval($size) * 1024 * 1024 * 1024;
		return $bytes;
	}
	
	/**
	 * Get the directory path to the base uploads directory, not including date-specific sub-directories.
	 *
	 * @return string The path
	 */
	public function get_uploads_basedir()
	{
		return $this->_basedir;
	}
	
	/**
	 * Get the uploads directory path, including date-based sub-directory; in other words, where the files
	 *	should live.
	 *
	 * @return string The path to the date-based sub-directory.
	 */
	public function get_uploads_dir()
	{
		return $this->_path;
	}
	
	/**
	 * Get the uploads directory base URL, not including the date-specific sub-directories.
	 *
	 * @return string The URL to the base directory.
	 */
	public function get_uploads_baseurl()
	{
		return $this->_baseurl;
	}
	
	/**
	 * Get the uploads directory URL with date-based sub-directory.
	 *
	 * @return string The URL to the sub-directory.
	 */
	public function get_uploads_url()
	{
		return $this->_url;
	}

	/**
	 * Get the date-based sub-directory of the uploads directory path, like '2011/05'
	 *
	 * @return string The date-based subdirectory path.
	 */
	public function get_uploads_subdir()
	{
		return $this->_baseurl;
	}

	/**
	 * Create an attachment from the given file
	 *
	 * @param string $file The path to the file.
	 * @param int $user_id. Optional. The ID of the user to associate with this
	 * 	attachment.
	 * @param string $url. Optional. The URL to the file.
	 * 	If empty, assumes that the base directory is uploads directory.
	 * @return int The ID of the attachment created.
	 */
	public function save_file_as_attachment( $file = '', $user_id = 0, $url = '' )
	{
		$user_id = (int) $user_id;
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// array( 'ext', 'type', 'proper_filename' )
		$types = wp_check_filetype_and_ext( $file, $file );
		if ( ! empty( $types[ 'proper_filename' ] ) ) {
			$name = $types[ 'proper_filename' ];
		}

		$current_user = new WP_User( $user_id );
	
		if ( 
			(
				empty( $types['type'] ) || empty( $types['ext'] ) 
			) && ! $current_user->has_cap( 'unfiltered_upload' )
		) {
			return 0;
		}

		if ( empty( $types['ext'] ) ) {
			$ext = ltrim( strrchr( $name, '.'), '.');
		}

		if ( empty( $url ) ) {
			$url = $this->get_uploads_url() . DIRECTORY_SEPARATOR . basename( $file );
		}

		$attachment = array(
			'post_author' => $user_id,
			'post_mime_type' => $types['type'],
			'guid' => $url,
			'post_title' => $title,
			'post_content' => $content,
		);

		$attachment = apply_filters( 'flex_uploader_attachment_properties', $attachment, $file );
		
		$id = wp_insert_attachment( $attachment, $file );

		if ( ! is_wp_error( $id ) ) {

			if ( ! function_exists('wp_generate_attachment_metadata') ) {	
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$data = wp_generate_attachment_metadata( $id, $file );
			wp_update_attachment_metadata( $id, $data );
			return $id;
		}

		return 0;
	}
}

class WP_Flexible_Uploader_View {

	public $model;

	public function print_form() 
	{
		do_action( 'wp_flexible_uploader_before_form' );
		?>
		<form enctype="multipart/form-data" id="<?php echo esc_attr( $this->model->form_id ); ?>" class="flexible-uploader-form" method="post" action="">
			<div id="<?php echo esc_attr( $this->model->upload_wrap_id ); ?>">
			
				<?php $this->print_form_extras(); ?>
				
				<input type="file" name="<?php echo esc_attr( $this->model->browse_button_id ); ?>" id="<?php echo esc_attr( $this->model->browse_button_id ); ?>" value="<?php echo esc_attr( __('Select Files', 'flexible-uploader' ) ); ?>" />

				<?php
				$submit_button = apply_filters( 'wp_flexible_uploader_form_submit', __('Upload', 'flexible-uploader') );
				if ( ! empty( $submit_button ) ) :
					?>
					<div>
						<input type="submit" value="<?php echo esc_attr( $submit_button ); ?>" />
					</div>
					<?php
				endif;
				?>
				<div id="<?php echo esc_attr( $this->model->progress_bar_wrap ); ?>" class="uploader-progress-bar-wrap">
					<div id="<?php echo esc_attr( $this->model->progress_bar_id ); ?>" class="uploader-progress-bar">
					</div><!-- #<?php echo $this->model->progress_bar_id; ?> -->
				</div><!-- #<?php echo $this->model->progress_bar_wrap; ?> -->
			</div>
		</form>
		<?php
		do_action( 'wp_flexible_uploader_after_form' );
	}

	public function print_form_extras() 
	{
		$header = apply_filters( 'wp_flexible_uploader_form_header', __( 'Upload a File', 'flexible-uploader' ) );
		$instructions = apply_filters( 'wp_flexible_uploader_form_instructions', __( 'Choose a file to upload.', 'flexible-uploader' ) );

		if ( ! empty( $header ) ) :
			?><h3><?php echo $header; ?></h3><?php
		endif;

		if ( ! empty( $instructions ) ) :
			?><p><?php echo $instructions; ?></p><?php	
		endif;

		$extra_fields = apply_filters( 'wp_flexible_uploader_form_extra_fields' , array() );

		foreach( (array) $extra_fields as $field ) :
			echo $field . "\n";
		endforeach;

	}
	
	public function print_header()
	{
		?>
		<script type="text/javascript">
		// <![CDATA[
		// Convert divs to queue widgets when the DOM is ready
		var flexibleUploader = new plupload.Uploader({
			// General settings
			// runtimes : 'gears,flash,silverlight,browserplus,html5',
			runtimes : 'flash',
			url : '<?php 
				echo add_query_arg( 
					array(
						$this->model->request_variable_id => 'file_submit',
						$this->model->file_name_id => $this->model->file_name_id,
					),
					esc_js( site_url( 'wp-load.php' ) )
				); 
			?>',
			browse_button: '<?php
				echo esc_js( $this->model->browse_button_id ); 
			?>',
			container: '<?php
				echo esc_js( $this->model->upload_wrap_id );	
			?>',
			max_file_size : '<?php echo $this->model->get_max_upload_size( 'mb' ); ?>mb',
			chunk_size : '<?php echo $this->model->get_chunk_size( 'mb' ); ?>mb',
			unique_names : false,

			// Resize images on clientside if we can
			// resize : {width : 222, height : 222, quality : 90},

			// Specify what files to browse for
			filters : [
				{title : "<?php 
					echo esc_js( __('Image files', 'flexible-uploader') );	
				?>", extensions : "jpg,gif,png"}
			],

			// Flash settings
			flash_swf_url : '<?php
				echo plugins_url(
					'client-files/js/plupload.flash.swf',
					dirname( __FILE__ )
				);
			?>',

			// Silverlight settings
			silverlight_xap_url : '<?php
				echo plugins_url(
					'client-files/js/plupload.silverlight.xap',
					dirname( __FILE__ )
				);
			?>'
		}),

		flexibleUploaderBrowseButtonId = '<?php
			echo esc_js( $this->model->browse_button_id ); 
		?>',
		
		flexibleUploaderContainerId = '<?php
			echo esc_js( $this->model->upload_wrap_id );	
		?>',

		flexibleUploaderFormId = '<?php
			echo esc_js( $this->model->form_id ); 
		?>',

		flexibleUploaderIsAdmin = '<?php
			echo esc_js( is_admin() ? '1' : '0' ); 
		?>',

		flexibleUploaderProgressBarId = '<?php
			echo esc_js( $this->model->progress_bar_id );
		?>',
		
		flexibleUploaderProgressBarWrap = '<?php
			echo esc_js( $this->model->progress_bar_wrap );
		?>'
		// ]]>
		</script>
		<?php
	}
}

class WP_Flexible_Uploader_Endpoints {

	public $model;
	public $view;

	public $uploads_dir = '';
	public $uploads_url = '';

	public function __construct()
	{
		
	}
	
	public function listen_for_form_requests()
	{
		if ( 
			! empty( $_REQUEST[ $this->model->request_variable_id ] ) && 
			'get-upload-form' == $_REQUEST[ $this->model->request_variable_id ] &&
			$this->model->user_can_view_form( get_current_user_id() )
		) {
			$this->view->print_form();

			if ( ! empty( $_REQUEST['ajax-request'] ) ) {
				exit;
			}
		}
	}

	public function listen_for_submissions()
	{
		if ( 
			! empty( $_REQUEST[ $this->model->request_variable_id ] ) && 
			'file_submit' == $_REQUEST[ $this->model->request_variable_id ] &&
			$this->model->user_can_upload( get_current_user_id() )
		) {
			$this->process_upload( 'file-submissions' );
		}
	}

	public function process_upload( $context = 'unknown' )
	{
		// 5 minutes execution time
		@set_time_limit(5 * 60);

		$chunk = isset( $_REQUEST["chunk"] ) ? $_REQUEST["chunk"] : 0;
		$chunks = isset( $_REQUEST["chunks"] ) ? $_REQUEST["chunks"] : 0;
		$file_name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

		// Clean the fileName for security reasons
		$file_name = preg_replace('/[^\w\._-]+/', '', $file_name);

		$target_dir = $this->model->get_uploads_dir();

		if ( ! file_exists( $target_dir ) ) {
			@mkdir( $target_dir );
		}

		$max_temp_age = 60 * 60;

		// HTTP headers for no cache etc
		header('Content-type: text/plain; charset=UTF-8');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		if ( is_dir( $target_dir ) && ( $dir = opendir( $target_dir ) ) ) {
			while ( ( $file = readdir ( $dir ) ) !== false ) {
				$file_path = $target_dir . DIRECTORY_SEPARATOR . $file;

				if ( preg_match( '/\\.tmp$/', $file ) && ( filemtime( $file_path ) < ( time() - $max_temp_age ) ) ) {
					@unlink( $file_path );
				}
			}

			closedir( $dir );
		} else {
			echo json_encode( array(
				'jsonrpc' => '2.0',
				'error' => array(
					'code' => 100,
					'message' =>  __( 'Failed to open temporary directory.', 'flexible-uploader' ), 
				),
				'id' => 'id',
			) );
			exit;
		}

		if ( isset( $_SERVER[ 'HTTP_CONTENT_TYPE' ] ) ) {
			$content_type =  $_SERVER[ 'HTTP_CONTENT_TYPE' ];
		}

		if ( isset( $_SERVER[ 'CONTENT_TYPE' ] ) ) {
			$content_type = $_SERVER[ 'CONTENT_TYPE' ];
		}

		if ( strpos( $content_type, 'multipart' ) !== false ) {
			if ( 
				isset( $_FILES[ $this->model->file_name_id ]['tmp_name'] ) && 
				is_uploaded_file( $_FILES[ $this->model->file_name_id ]['tmp_name'] )
			) {
				$out = fopen( $target_dir . DIRECTORY_SEPARATOR . $file_name, $chunk == 0 ? 'wb' : 'ab' );

				if ( $out ) {
					$in = fopen( $_FILES[ $this->model->file_name_id ]['tmp_name'], 'rb' );

					if ( $in ) {
						while ( $buff = fread( $in, 4096 ) ) {
							fwrite( $out, $buff );
						}
					} else {
						echo json_encode( array(
							'jsonrpc' => '2.0',
							'error' => array(
								'code' => 101,
								'message' =>  __( 'Failed to open input stream.', 'flexible-uploader' ), 
							),
							'id' => 'id',
						) );
						exit;
					}

					fclose( $out );
					unlink( $_FILES[ $this->model->file_name_id ]['tmp_name'] );
				} else {
					echo json_encode( array(
						'jsonrpc' => '2.0',
						'error' => array(
							'code' => 102,
							'message' =>  __( 'Failed to open output stream.', 'flexible-uploader' ), 
						),
						'id' => 'id',
					) );
					exit;
				}
			} else {
				echo json_encode( array(
					'jsonrpc' => '2.0',
					'error' => array(
						'code' => 103,
						'message' =>  __( 'Failed to move uploaded file.', 'flexible-uploader' ), 
					),
					'id' => 'id',
				) );
				exit;
			}
		} else {
			
			$out = fopen( $target_dir . DIRECTORY_SEPARATOR . $file_name, $chunk == 0 ? 'wb' : 'ab' );

			if ( $out ) {
				$in = fopen( 'php://input', 'rb' );

				if ( $in ) {
					while ( $buff = fread( $in, 4096 ) ) {
						fwrite ( $out, $buff );
					}
				} else {
					echo json_encode( array(
					'jsonrpc' => '2.0',
					'error' => array(
						'code' => 101,
						'message' =>  __( 'Failed to open input stream.', 'flexible-uploader' ), 
					),
					'id' => 'id',
					) );
					exit;
				}

				fclose( $out );
			} else {
				echo json_encode( array(
					'jsonrpc' => '2.0',
					'error' => array(
						'code' => 102,
						'message' =>  __( 'Failed to open output stream.', 'flexible-uploader' ), 
					),
					'id' => 'id',
				) );
				exit;
			}
		}

		if ( isset( $_REQUEST[ 'chunk' ] ) && isset( $_REQUEST[ 'chunks' ] ) ) {
			// don't save when we haven't finished uploading
			if ( $chunk != ( $chunks - 1 ) ) {
				die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
			}
		}

		$file_path = $target_dir . DIRECTORY_SEPARATOR . $file_name;
		
		$attach_id = $this->model->save_file_as_attachment( $file_path, get_current_user_id() );

		do_action( 'wp_flexible_uploader_created_attachment', $attach_id, $file_path, $file_name, $context );

		echo json_encode( array(
			'jsonrpc' => '2.0',
			'result' => $file_path,
			'id' => 'id',
		) );
		exit;
	}
}

function load_flexible_uploader_plugin()
{
	global $wp_flexible_uploader;
	$control = apply_filters( 'wp_flexible_uploader_control', 'WP_Flexible_Uploader_Control' ); 
	$wp_flexible_uploader = new $control;
}

add_action('plugins_loaded', 'load_flexible_uploader_plugin');
// eof
