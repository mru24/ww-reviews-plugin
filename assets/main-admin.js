(function ($, root, undefined) {

	$(function () {

		var app = {
			init() {
				console.log("WW-Reviews - Admin - Ready - WW 03/2023");

				this.jq = jQuery;
				this.sendEmail = this.jq('.ww-send-email');
				this.sendEmail.on('submit',(e)=>{ this.sendReviewEmail(e); });
			},
			sendReviewEmail : async function(e) {
				e.preventDefault();
				var target = e.currentTarget;
				var $form = this.jq(target);
        var id = $form.find('input[name="id"]').val();
        var email = $form.find('input[name="email"]').val();
        var nonce = $form.find('input[name="_wpnonce"]').val();

        var $button = $form.find('input[type="submit"]');
        var $spinner = $form.find('.ww-spinner');

				if(email) {
					$button.prop('disabled', true);
            $spinner.addClass('is-active').show();
		    	this.jq.ajax({
						type: 'POST',
			      url: ajaxurl,
			      data: {
              action: 'sendReviewEmail',
              id: id,
              email: email,
              _wpnonce: nonce
            },
			      success: function (data) {
							$button.val('Sent ✓');
							$button.prop('disabled', false);
              $spinner.removeClass('is-active').hide();
			      },
						error: function (data) {
							$button.prop('disabled', false);
              $spinner.removeClass('is-active').hide();
						}
			    });
				}
			}
		}
		app.init();
	});

})(jQuery, this);