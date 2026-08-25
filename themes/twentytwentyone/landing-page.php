<?php
/**
 * Template Name: Landing Page
 */
get_header('landing'); 
?>

<?php
/* Start the Loop */
while ( have_posts() ) :
	the_post();

	$thumbnail = get_the_post_thumbnail_url();
	
?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</article>
<?php

endwhile; // End of the loop.
?>

<div id="register-popup" class="register-popup">
  <div class="popup-content">
     <span class="close-popup">
			<svg width="24" height="33" viewBox="0 0 24 33" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M23.4417 5.05833C23.0841 4.70083 22.5991 4.5 22.0935 4.5C21.5878 4.5 21.1029 4.70083 20.7453 5.05833L12 13.8036L3.25475 5.05833C2.89714 4.70083 2.41219 4.5 1.90654 4.5C1.40088 4.5 0.915933 4.70083 0.558328 5.05833C0.200831 5.41593 0 5.90088 0 6.40654C0 6.91219 0.200831 7.39714 0.558328 7.75475L9.30358 16.5L0.558328 25.2453C0.200831 25.6029 0 26.0878 0 26.5935C0 27.0991 0.200831 27.5841 0.558328 27.9417C0.915933 28.2992 1.40088 28.5 1.90654 28.5C2.41219 28.5 2.89714 28.2992 3.25475 27.9417L12 19.1964L20.7453 27.9417C21.1029 28.2992 21.5878 28.5 22.0935 28.5C22.5991 28.5 23.0841 28.2992 23.4417 27.9417C23.7992 27.5841 24 27.0991 24 26.5935C24 26.0878 23.7992 25.6029 23.4417 25.2453L14.6964 16.5L23.4417 7.75475C23.7992 7.39714 24 6.91219 24 6.40654C24 5.90088 23.7992 5.41593 23.4417 5.05833Z" fill="black"/>
			</svg>
		</span>

    <?= do_shortcode('[contact-form-7 id="2988d20" title="GiftCard Register"]') ?>
	<div class="thankyou-message"></div>
    <div class="modal-footer">
      <button class="close-popup">Close</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const popup = document.getElementById("register-popup");
  const closeBtn = popup.querySelectorAll(".close-popup");

  document.querySelectorAll(".register-form-popup > button").forEach(btn => {
    btn.addEventListener("click", () => {
      document.body.classList.add("popup-open");
    });
  });

	closeBtn.forEach(btn => {
	btn.addEventListener("click", () => {
		document.body.classList.remove("popup-open");

		const content = popup.querySelector('.thankyou-message');
		content.innerHTML = "";

		const form = popup.querySelector("form.wpcf7-form");
		if (form) {
		form.reset(); 
		}

		const responseOutput = popup.querySelector(".wpcf7-response-output");
		if (responseOutput) {
		responseOutput.innerHTML = "";
		responseOutput.classList.remove("wpcf7-mail-sent-ok", "wpcf7-validation-errors", "wpcf7-mail-sent-ng");
		}
	});
	});

  popup.addEventListener("click", (e) => {
    if (e.target === popup) {
      document.body.classList.remove("popup-open");
    }
  });


  var wpcf7Elm = document.querySelector( '.register-popup .wpcf7' );
 
	wpcf7Elm.addEventListener( 'wpcf7mailsent', function( event ) {
		setTimeout(function(){
			const content = popup.querySelector('.thankyou-message');
			content.innerHTML = `
				<svg width="80" height="81" viewBox="0 0 80 81" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M40 0.5C17.92 0.5 0 18.42 0 40.5C0 62.58 17.92 80.5 40 80.5C62.08 80.5 80 62.58 80 40.5C80 18.42 62.08 0.5 40 0.5ZM40 72.5C22.36 72.5 8 58.14 8 40.5C8 22.86 22.36 8.5 40 8.5C57.64 8.5 72 22.86 72 40.5C72 58.14 57.64 72.5 40 72.5ZM55.52 25.66L32 49.18L24.48 41.66C22.92 40.1 20.4 40.1 18.84 41.66C17.28 43.22 17.28 45.74 18.84 47.3L29.2 57.66C30.76 59.22 33.28 59.22 34.84 57.66L61.2 31.3C62.76 29.74 62.76 27.22 61.2 25.66C59.64 24.1 57.08 24.1 55.52 25.66Z" fill="#67D6C8"/>
				</svg>
				<h3>Thanks for pre-registering!</h3>
				<p>You have gone into the draw to win a $100 Gift Card.</p>
				<p>We will be in touch regarding your prize and our giftcards<i>plus</i> website soon.</p>
			`;
		},500)

	}, false );


});
</script>


<?php get_footer('landing');?>
