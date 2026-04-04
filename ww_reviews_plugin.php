<?php

/**
 * @package WW Reviews Plugin
 *
 */

 /*
 Plugin Name: WW Reviews Plugin
 Plugin URI:
 Description: WordPress plugin for managing customer reviews with star ratings, custom templates, and email invitations.
 Version: 3.1.1
 Author: Val Wroblewski
 Author URI:
 Licence: GPLv2 or later
 Text-Domain: ww-reviews-domain
  */

if( !defined( 'ABSPATH' )) { die; }

define( 'PLUGIN_DIR', dirname(__FILE__).'/' );

require_once 'includes/ReviewMetaBoxClass.php';

class WWReviewsPlugin {
	public $plugin;
  public $plugin_version="3.1.1";
	public $plugin_file;
	public $plugin_nice_name;
	public $textDomain;
	public $custom_post;
	public $admin_page;

	// SETTINGS
	public $settings;
	public $settings_group;
	public $email;

	function __construct() {
		$this->plugin = 'ww_reviews';
		$this->plugin_file = plugin_basename( __FILE__ );
		$this->plugin_nice_name = 'WW Reviews';
		$this->textDomain = $this->plugin.'_domain';
		$this->admin_page = $this->plugin.'_admin';
		$this->custom_post = $this->plugin.'_cp';

		// SETTINGS
		$this->settings = $this->plugin.'_settings';
		$this->settings_group = $this->plugin.'_settings_group';
	}
	function register() {

		// REGISTER CUSTOM POST
		add_action( 'init', array( $this, 'register_cp1' ));

		// REGISTER SCRIPTS & STYLES
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ));
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ));
		add_action( 'admin_menu', array( $this, 'add_submenu_page_to_post_type' ) );

		// PLUGINS PAGE SETTINGS LINK
		add_filter( "plugin_action_links_$this->plugin_file", array( $this, 'plugins_page_settings_link' ));

		// REGISTER SETTING
		add_action('admin_init', array($this, 'register_settings'));

		// REGISTER SHORTCODES
		add_action('init', array($this, 'register_shortcodes'));

		// AJAX
		add_action('wp_ajax_sendReviewEmail', array($this,'sendReviewEmail'));

    add_action('wp_ajax_submit_user_review', array($this, 'handle_review_submission'));
    add_action('wp_ajax_nopriv_submit_user_review', array($this, 'handle_review_submission'));

    add_action('admin_enqueue_scripts', function() {
      wp_localize_script('ww-scripts', 'ww_auth', array(
        'nonce' => wp_create_nonce('ww_reviews_nonce')
      ));
    });

    // COLOR PICKER
    add_action('admin_footer', [$this, 'admin_footer_script']);
	}
	function activate() {
		flush_rewrite_rules();
	}
	function deactivate () {
		flush_rewrite_rules();
	}
	function uninstall() {

	}
	function register_cp1() {
		register_post_type($this->custom_post,
			array(
				'public' => true,
				'labels' => array(
					'name' => __($this->plugin_nice_name,$this->textDomain),
					'singular_name' => __($this->plugin_nice_name,$this->textDomain),
				),
				'has_archive'	=> true,
				'rewrite'	=>	array('slug' => 'review'),
				'menu_position' => 5,
				'menu_icon' => 'dashicons-format-quote',
				'supports' => array(
					'title', 'editor', 'custom-fields','excerpt','thumbnail',
				),
			)
		);
	}
	function enqueue_admin_scripts() {
		wp_enqueue_style( 'ww-styles', plugins_url( '/assets/styles-admin.css', __FILE__ ) );
    wp_enqueue_script('ww-scripts', plugins_url('/assets/main-admin.js', __FILE__), array('jquery'), $this->plugin_version, true);
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
	}
	function enqueue_frontend_scripts() {
		wp_enqueue_style( 'ww-styles', plugins_url( '/assets/styles.css', __FILE__ ) );
		wp_enqueue_script( 'ww-scripts', plugins_url( '/assets/main.js', __FILE__ ), array('jquery'), $this->plugin_version, true);
	}
	public function add_submenu_page_to_post_type() {
    add_submenu_page(
      'edit.php?post_type='.$this->custom_post,
      __('Settings', $this->textDomain),
      __('Settings', $this->textDomain),
      'edit_posts',
      $this->admin_page,
      array($this, 'admin_page_display'));
	}
	public function admin_page_display() {
		require_once 'templates/settings.php';
	}
	public function plugins_page_settings_link( $links ) {
		$settings_link = '<a href="edit.php?post_type='.$this->custom_post.'&page='.$this->admin_page.'">Settings</a>';
		array_push( $links, $settings_link );
		return $links;
	}
	public function register_settings() {
		add_settings_section('ww_first_admin_section', null, null, $this->admin_page);
		$items = array(
      ['name'=>'_spacer_1', 'method'=>'spacerHtml', 'label'=>'<h3>Email Template</h3>'],
			['name'=>'_email_subject', 'method'=>'emailSubjectHtml', 'label'=>'Email subject'],
			['name'=>'_email_template', 'method'=>'emailTemplateHtml', 'label'=>'Email template'],
      ['name'=>'_spacer_2', 'method'=>'spacerHtml', 'label'=>'<h3>Reviews List</h3>'],
			['name'=>'_list_template', 'method'=>'listTemplateHtml', 'label'=>'List template'],
			['name'=>'_list_template_styles', 'method'=>'listTemplateStylesHtml', 'label'=>'List template styles'],
			['name'=>'_list_template_fields', 'method'=>'listTemplateFieldsHtml', 'label'=>'List template fields'],
      ['name'=>'_star_color', 'method'=>'starColorPicker', 'label'=>'Review Star Colour'],
      ['name'=>'_spacer_3', 'method'=>'spacerHtml', 'label'=>'<h3>Submit Review Form</h3>'],
      ['name'=>'_submit_form_styles', 'method'=>'submitFormStylesHtml', 'label'=>'Submit Form styles'],
      ['name'=>'_spacer_4', 'method'=>'spacerHtml', 'label'=>'<h3>Shortcodes</h3>'],
			['name'=>'_list_shortcode', 'method'=>'listShortcodeHtml', 'label'=>'List shortcode'],
			['name'=>'_form_shortcode', 'method'=>'submitFormShortcodeHtml', 'label'=>'Submit Form shortcode'],
		);
		foreach ($items as $item) {
      add_settings_field($this->plugin.$item['name'], $item['label'], array($this, $item['method']), $this->admin_page, 'ww_first_admin_section');
      if (!empty($item['name'])) {
        register_setting(
          $this->settings_group,
          $this->plugin.$item['name'],
          array(
            'sanitize_callback' => null
          )
		    );
      }
    }
	}
  public function spacerHtml() {
    echo '<hr style="margin: 10px 0; border: 0; border-top: 2px solid #ccc;" />';
  }
	public function emailSubjectHtml() { ?>
		<input type="text" name="<?php echo $this->plugin.'_email_subject'; ?>" value="<?php echo get_option($this->plugin.'_email_subject') ?>" style="width:80%;" />
  <?php
	}
  public function emailTemplateHtml() {
  	$option_name = $this->plugin . '_email_template';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/email-template.html';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    }
    $settings = array(
      'teeny' => false,
        'textarea_rows' => 15,
        'tabindex' => 1
    );
    wp_editor(stripslashes($content),$option_name,$settings);
  }
	public function listTemplateHtml() {
    $option_name = $this->plugin . '_list_template';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/list-template.html';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
    <textarea
        name="<?php echo esc_attr($option_name); ?>"
        style="width:100%;height:350px;resize:none;"
        <?php if (!current_user_can('manage_options')) echo "readonly"; ?>
    ><?php echo esc_textarea($content); ?></textarea>
  <?php
  }
	public function listTemplateStylesHtml() {
    $option_name = $this->plugin . '_list_template_styles';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/list-template-styles.css';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
		<textarea name="<?php echo esc_attr($option_name); ?>" style="width:100%;height:200px;resize:none;" <?php if(!current_user_can('manage_options')) echo "readonly"; ?>><?php echo esc_textarea($content); ?></textarea>
  <?php
	}
	public function listTemplateFieldsHtml() { ?>
		<textarea style="width:100%;height:80px;resize:none;font-size: 19px;" readonly>[name] [info] [excerpt] [content] [date] [stars]</textarea>
  <?php
	}
	public function listShortcodeHtml() { ?>
		<textarea style="width:100%;height:50px;resize:vertical;" readonly><?php echo '[ww_reviews]'; ?></textarea>
  <?php
	}
  public function submitFormStylesHtml() {
    $option_name = $this->plugin . '_submit_form_styles';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/form-styles.css';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
		<textarea name="<?php echo esc_attr($option_name); ?>" style="width:100%;height:200px;resize:none;" <?php if(!current_user_can('manage_options')) echo "readonly"; ?>><?php echo esc_textarea($content); ?></textarea>
  <?php
	}
  public function submitFormShortcodeHtml() { ?>
		<textarea style="width:100%;height:50px;resize:vertical;" readonly><?php echo '[ww_review_form]'; ?></textarea>
  <?php
	}
  public function starColorPicker() { ?>
    <input type="text"
        name="<?php echo $this->plugin.'_star_color'; ?>"
        value="<?php echo esc_attr(get_option($this->plugin.'_star_color')); ?>"
        class="my-color-field"
        data-default-color="#FFCC00" />
  <?php
  }
  public function admin_footer_script() { ?>
    <script>
      // COLOR PICKER
      jQuery(document).ready(function($){
        $('.my-color-field').wpColorPicker();
      });
    </script>
  <?php
  }

	//SHORTCODES
	public function register_shortcodes() {
		add_shortcode( 'ww_reviews', array($this, 'create_wp_query_shortcode' ));
    add_shortcode('ww_review_form', array($this, 'render_review_form'));
	}
	public function create_wp_query_shortcode() {
    $unique_id = 'ww-reviews-' . uniqid();
    $loop = new WP_Query(array(
      'post_type' => $this->custom_post,
      'orderby' => 'post_id',
      'order' => 'DESC',
      'posts_per_page' => -1,
    ));
    $reviews_html = '';
    $raw_template = get_option($this->plugin.'_list_template');
    $starColor    = esc_attr(get_option($this->plugin.'_star_color'));
    while ($loop->have_posts()) {
      $loop->the_post();
      $post_id = get_the_ID();
      $status   = get_post_meta($post_id, 'ww_review_active', true);
      if ($status) {
        $name     = strip_tags(get_the_title());
        $content  = strip_tags(get_the_content());
        $excerpt  = strip_tags(get_the_excerpt());
        $raw_info = strip_tags(get_post_meta($post_id, 'ww_review_info', true));
        $raw_date = get_post_meta($post_id, 'ww_review_date', true);
        $date     = $raw_date ? explode('-', strip_tags($raw_date)) : ['00', '00', '0000'];
        $dateReady = isset($date[2]) ? $date[2].'/'.$date[1].'/'.$date[0] : '';
        $email    = strip_tags(get_post_meta($post_id, 'ww_review_email', true));
        $tel      = strip_tags(get_post_meta($post_id, 'ww_review_tel', true));
        $stars    = strip_tags(get_post_meta($post_id, 'ww_review_stars', true));
        $bg       = strip_tags(get_post_meta($post_id, 'ww_review_bg', true));
        $col      = strip_tags(get_post_meta($post_id, 'ww_review_col', true));

        $info = !empty($raw_info) ? '<span class="ww-review-info"> - ' . $raw_info . '</span>' : '';

        $starsHtml = '<span class="ww-review-stars-container">';
        for ($i = 1; $i <= 5; $i++) {
          $color = ($i <= (int)$stars) ? $starColor : '#cccccc';
          $starsHtml .= '<span style="color:' . $color . '; font-size: 25px;">&#9733;</span>';
        }
        $starsHtml .= '</span>';
        $templateData = array(
          'name'    => $name,
          'content' => $content,
          'excerpt' => $excerpt,
          'info'    => $info,
          'email'   => $email,
          'tel'     => $tel,
          'date'    => $dateReady,
          'stars'   => $starsHtml,
          'bg'      => $bg,
          'col'     => $col,
        );
        $text = $raw_template;
        foreach ($templateData as $key => $value){
          $text = str_replace("[$key]", $value, $text);
        }
        $reviews_html .= $text;
      }
    }
    $output = '<div id="' . $unique_id . '" class="ww-reviews-wrapper">';
    $output .= $reviews_html;
    $output .= '</div>';
    $raw_css = get_option($this->plugin.'_list_template_styles');
    $scoped_css = preg_replace_callback(
      '/@media[^{]+\{([\s\S]+?\})\s*\}/',
      function ($matches) use ($unique_id) {
        $inner = preg_replace(
          '/([^\r\n,{}]+)(?=[^{}]*\{)/',
          '#' . $unique_id . ' $1',
          $matches[1]
        );
        return str_replace($matches[1], $inner, $matches[0]);
      },
      $raw_css
    );
    $scoped_css = preg_replace(
      '/(^|})([^{@}]+){/',
      '$1#' . $unique_id . ' $2{',
      $scoped_css
    );
    $style_tag = '<style>' . $scoped_css . '</style>';
    return $style_tag . $output;
  }
  public function render_review_form() {
    $unique_id = 'ww-form-' . uniqid();
    $raw_css   = get_option($this->plugin . '_submit_form_styles');

    ob_start(); ?>
    <div id="<?php echo $unique_id; ?>" class="ww-review-form-container">
      <form id="ww-review-form" class="ww-submission-form">
        <?php wp_nonce_field('ww_review_submit_nonce', 'ww_nonce'); ?>

        <div class="form-group">
          <label>Name</label>
          <input type="text" name="reviewer_name" required>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="reviewer_email" required>
        </div>

        <div class="form-group">
          <label>Rating</label>
          <div class="ww-star-rating">
            <?php
            for ($i = 5; $i >= 1; $i--):
              $star_id = "star-{$unique_id}-{$i}";
            ?>
              <input type="radio" id="<?php echo $star_id; ?>" name="reviewer_stars" value="<?php echo $i; ?>" />
              <label for="<?php echo $star_id; ?>" title="<?php echo $i; ?> stars">&#9733;</label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Review</label>
          <textarea name="reviewer_content" required></textarea>
        </div>

        <div class="form-group">
          <label>Extra Info (e.g. Location)</label>
          <input type="text" name="reviewer_info">
        </div>

        <button type="submit" class="ww-submit-btn btn btn-primary">Submit Review</button>
        <div id="form-message"></div>
      </form>

      <style>
        #<?php echo $unique_id; ?> button[type="submit"] {
          border: none;
          padding: 10px 21px;
          border-radius: 5px;
          margin: 5px 15px 15px;
          transition: 0.5s;
          cursor: pointer;
        }
        #<?php echo $unique_id; ?> button[type="submit"]:hover {
          filter: brightness(0.8);
        }
        #<?php echo $unique_id; ?> .ww-star-rating {
          display: flex;
          flex-direction: row-reverse;
          justify-content: flex-end;
        }
        #<?php echo $unique_id; ?> .ww-star-rating input { display: none !important; }
        #<?php echo $unique_id; ?> .ww-star-rating label {
          font-size: 32px;
          color: #ccc;
          cursor: pointer;
          padding: 0 2px;
          transition: color 0.2s ease-in-out;
        }
        #<?php echo $unique_id; ?> .ww-star-rating label:hover,
        #<?php echo $unique_id; ?> .ww-star-rating label:hover ~ label,
        #<?php echo $unique_id; ?> .ww-star-rating input:checked ~ label {
          color: #FFCC00 !important;
        }
        <?php echo $raw_css; ?>
      </style>

      <script>
        jQuery(document).ready(function($) {
          var $wrapper = $('#<?php echo $unique_id; ?>');
          var $form = $wrapper.find('#ww-review-form');

          $form.on('submit', function(e) {
            e.preventDefault();
              var rating = $form.find("input[name='reviewer_stars']:checked").val();
              if (!rating) {
                alert('Please select a star rating!');
                return;
              }
              var formData = $form.serialize();
              $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData + '&action=submit_user_review',
                beforeSend: function() {
                  $form.find('.ww-submit-btn').prop('disabled', true).text('Processing...');
                },
                success: function(response) {
                  if(response.success) {
                    $form.find('#form-message').html('<p style="color:green; font-weight:bold;">' + response.data + '</p>');
                    $form[0].reset();
                  } else {
                    $form.find('#form-message').html('<p style="color:red;">' + response.data + '</p>');
                  }
                },
                error: function() {
                  $form.find('#form-message').html('<p style="color:red;">Connection error. Try again.</p>');
                },
                complete: function() {
                  $form.find('.ww-submit-btn').prop('disabled', false).text('Submit Review');
                }
              });
          });
        });
      </script>
    </div>
    <?php
    return ob_get_clean();
  }
  public function handle_review_submission() {
    check_ajax_referer('ww_review_submit_nonce', 'ww_nonce');

    $name    = sanitize_text_field($_POST['reviewer_name']);
    $email   = sanitize_email($_POST['reviewer_email']);
    $content = sanitize_textarea_field($_POST['reviewer_content']);
    $stars   = intval($_POST['reviewer_stars']);
    $info    = sanitize_text_field($_POST['reviewer_info']);
    $post_id = wp_insert_post(array(
      'post_title'   => $name,
      'post_content' => $content,
      'post_status'  => 'pending',
      'post_type'    => $this->custom_post,
    ));
    if ($post_id) {
      update_post_meta($post_id, 'ww_review_stars', $stars);
      update_post_meta($post_id, 'ww_review_email', $email);
      update_post_meta($post_id, 'ww_review_info', $info);
      update_post_meta($post_id, 'ww_review_date', date('Y-m-d'));
      update_post_meta($post_id, 'ww_review_active', 0);
      wp_send_json_success('Thank you! Your review is awaiting approval.');
    } else {
      wp_send_json_error('Could not save review.');
    }
  }
	public function sendReviewEmail() {
		if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'send_review_email') ) {
      wp_die('Security check failed');
    }
    if ( ! current_user_can('edit_posts') ) {
      wp_die('Unauthorized');
    }
    $to = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $postID = isset($_POST['id']) ? absint($_POST['id']) : 0;

    if ( empty($to) || ! is_email($to) || $postID <= 0 ) {
      return;
    }

    $date = date('d.m.Y');
    $name = get_option('blogname');
    $email = get_option('admin_email');
    $subject = sanitize_text_field(get_option($this->plugin.'_email_subject'));

    $message = wp_kses_post(wpautop(get_option($this->plugin.'_email_template')));

    $headers = array(
      'Content-type: text/html; charset=utf-8',
      'From: ' . $name . ' <' . $email . '>'
    );

    $sent = wp_mail($to, $subject, $message, $headers);

    if ( $sent ) {
      update_post_meta($postID, 'ww_review_email_sent', $date);
    } else {
      error_log('Review email failed for post ID: ' . $postID);
    }
  }
}

if( class_exists('WWReviewsPlugin')) {
	$wwReview = new WWReviewsPlugin();
	$wwReview->register();
}

// activation
register_activation_hook( __FILE__, array($wwReview,'activate') );

// deactivation
register_deactivation_hook(__FILE__, array($wwReview, 'deactivate'));

// uninstall

