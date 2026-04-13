<div class="wrap">
  <div id="tabs">
    <div class="tabnav">
      <button class="tablinks active" onclick="openTab(event, 'reviews')">Reviews</button>
      <button class="tablinks" onclick="openTab(event, 'settings')">Settings</button>
    </div>
    <div id="reviews" class="tab active">
      <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
          <th class="manage-column column-title column-primary">Title</th>
          <th class="manage-column column-date">Location</th>
          <th class="manage-column column-date" style="width:100px;">Displayed</th>
          <th class="manage-column column-date" style="width:100px;">Date</th>
          <th class="manage-column column-email">Email</th>
          <th class="manage-column column-email">Send email</th>
          <th class="manage-column column-email">Email sent</th>
          <th class="manage-column column-email">Edit</th>
        </thead>
        <tbody>
          <?php
            $loop = new WP_Query( array(
              'post_type' => $this->custom_post,
              'orderby' => 'post_id',
              'order' => 'DESC',
              'posts_per_page' => -1,
            ) );
            while( $loop->have_posts() ) : $loop->the_post();
              $status = get_post_meta(get_the_ID(), 'ww_review_active', true);
              $location = get_post_meta(get_the_ID(), 'ww_review_info', true);
              $date = get_post_meta(get_the_ID(), 'ww_review_date', true);
              $email = get_post_meta(get_the_ID(), 'ww_review_email', true);
              $sent = get_post_meta(get_the_ID(), 'ww_review_email_sent', true);
              $sentDate = get_post_meta(get_the_ID(), 'ww_review_email_sent_date', true);
              $tel = get_post_meta(get_the_ID(), 'ww_review_tel', true);
              $bg = get_post_meta(get_the_ID(), 'ww_review_bg', true);
              $col = get_post_meta(get_the_ID(), 'ww_review_col', true);
          ?>
          <tr class="iedit author-self level-0 status-publish hentry">
            <td class="title column-title has-row-actions column-primary page-title"><?php the_title() ?></td>
            <td><?php echo $location ?></td>
            <td><?php echo $status?'<span class="active">Active</span>':'<span class="disabled">Disabled</span>'; ?></td>
            <td><?php echo $date ?></td>
            <td><?php echo $email ?></td>
            <td>
              <?php if(!$sent) : ?>
              <form method="post" class="ww-send-email">
              	<?php wp_nonce_field('send_review_email', '_wpnonce'); ?>
                <input type="hidden" name="id" value="<?php the_ID() ?>">
                <input type="hidden" name="name" value="<?php the_title(); ?>">
                <input type="hidden" name="email" value="<?php echo $email; ?>">
                <input class="btn " type="submit" name="<?php echo $this->plugin; ?>_send" value="Send email" />
                <span class="ww-spinner" style="display:none;"></span>
              </form>
              <?php else : ?>
              <form method="post" class="ww-send-email">
              	<?php wp_nonce_field('send_review_email', '_wpnonce'); ?>
                <input type="hidden" name="id" value="<?php the_ID() ?>">
                <input type="hidden" name="name" value="<?php the_title(); ?>">
                <input type="hidden" name="email" value="<?php echo $email; ?>">
                <input class="btn " type="submit" name="<?php echo $this->plugin; ?>_send" value="Resend email" />
                <span class="ww-spinner" style="display:none;"></span>
              </form>
              <?php endif; ?>
            </td>
            <td><?php echo $sent?'Email sent '.$sent:''; ?></td>
            <td><a href="/wp-admin/post.php?post=<?php echo the_ID(); ?>&action=edit">Edit</a></td>
          </tr>
          <?php endwhile; wp_reset_query(); ?>
        </tbody>
        <tfoot>
          <th class="manage-column column-title column-primary">Title</th>
          <th class="manage-column column-date">Location</th>
          <th class="manage-column column-date">Displayed</th>
          <th class="manage-column column-date">Date</th>
          <th class="manage-column column-email">Email</th>
          <th class="manage-column column-email">Send email</th>
          <th class="manage-column column-email">Email sent</th>
          <th class="manage-column column-email">Edit</th>
        </tfoot>
      </table>
    </div>

    <div id="settings" class="tab">
      <div>
        <form method="post" action="options.php">
          <?php
            settings_fields($this->settings_group);
            do_settings_sections($this->admin_page);
            submit_button();
          ?>
        </form>
      </div>
    </div>
  </div>
  <footer>
    <p><?php echo $this->plugin_nice_name . ' ' .$this->plugin_version; ?></p>
  </footer>
</div>

<style>
	.ww-spinner {
    display: inline-block;
    position: relative;
    top: 4px;
    width: 16px;
    height: 16px;
    margin-left: 8px;
    border: 2px solid #ccc;
    border-top-color: #007cba;
    border-radius: 50%;
    animation: ww-spin 0.6s linear infinite;
  }
  @keyframes ww-spin {
    to { transform: rotate(360deg); }
  }
</style>

<script>
  function openTab(evt, tabName) {
    var i, tab, tablinks;
    tab = document.getElementsByClassName("tab");
    for (i = 0; i < tab.length; i++) {
      tab[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
      tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
  }
</script>

