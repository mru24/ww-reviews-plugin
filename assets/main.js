(function ($, root, undefined) {

	$(function () {

		var app = {
			init() {
				console.log("WW-Reviews - 03/2023 - 03/2026");

				this.jq = jQuery;

				this.stars = this.jq('.reviews .stars');
				this.stars.length>0?this.initStars():'';

			},
			initStars : async function() {
				this.stars.each((indexP,ep)=>{
					var active = this.jq(ep).attr('data-value');
					var star = this.jq(ep).children('img');

					this.jq(star).each((indexC,ec)=>{
						if(indexC+1<=active){
							this.jq(ec).attr('src','/wp-content/uploads/2022/08/star-orange-solid.svg');
						}
					})
				})
			},

		}

		app.init();
	});

})(jQuery, this);