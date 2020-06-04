<?php
// Plugin Name: 11302 Feature Plugin
// Plugin URI: https://core.trac.wordpress.org/ticket/11302
// Description: Hacks for #11302 without altering Core. Let's do this!

// Inject some simple JavaScript, quick and dirty
add_action( 'admin_head', function() {
	// TODO: check $current_screen
	?>
		<style>
			input[name="post_category[]"] {
				-webkit-appearance: checkbox;
			}
		</style>

		<script>
			jQuery( document ).on( 'click', '#doaction', function( e ) {
				if ( 'edit' !== jQuery( 'select[name="action"]' ).val() ) {
					return; // Run only for edits
				}

				// Gather up some statistic on which of these checked posts are in which categories
				var checked_posts = jQuery( 'tbody th.check-column input[type="checkbox"]:checked' );
				var categories    = {};

				checked_posts.each( function() {
					var id      = jQuery( this ).val();
					var checked = jQuery( '#category_' + id ).text().split( ',' );

					checked.map( function( cid ) {
						categories[ cid ] || ( categories[ cid ] = 0 );
						categories[ cid ]++; // Just recored that this category is checked
					} );
				} );

				// Compute initial states
				jQuery( '.inline-edit-categories input[name="post_category[]"]' ).each( function() {
					jQuery( '<input type="hidden" name="indeterminate_post_category[]">' ).remove(); // Clear indeterminate states

					// If the number of checked categories matches the number of selected posts - then check, all are in this category
					if ( categories[ jQuery( this ).val() ] == checked_posts.length ) {
						jQuery( this ).prop( 'checked', true );

					// If the number is less than the number of selected posts - then it's indeterminate
					} else if ( categories[ jQuery( this ).val() ] > 0 ) {
						jQuery( this ).prop( 'indeterminate', true ); // TODO: I'm unable to get this to display like this: [-]
						// It seems to be an issue with -webkit-appearance: none; as per https://wordpress.slack.com/archives/C5UNMSU4R/p1591294075413300
						// Either way we need to figure out how to do this in the UI.

						// Set indeterminate states for the backend
						var hidden = jQuery( '<input type="hidden" name="indeterminate_post_category[]">' );
						hidden.val( jQuery( this ).val() );
						jQuery( this ).append( hidden );
						
					// If it's 0 then none of the selected posts are in this category, nothing to do
					} else {
					}
				} );

				jQuery( '.inline-edit-categories input[name="post_category[]"]' ).on( 'change', function() {
					// Remove the indeterminate flags as there was a specific state change.
					jQuery( this ).parent().find( 'input[name="indeterminate_post_category[]"]' ).remove();
				} );
			} );
		</script>
	<?php
} );


// Handle the backend terms.
add_action( 'set_object_terms', $closure = function( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) use ( &$closure ) {
	// Permissions have been validated.
	if ( ! isset( $_REQUEST['bulk_edit'] ) ) {
		return; // Only bulks.
	}

	if ( 'category' != $taxonomy ) {
		return; // We don't care for anything else in this proof-of-concept.
	}

	// It's pretty hard to alter the categories before they are inserted. We'll need to add a filter for sure.
	// For now we'll hack out way around it and just make sure we're not touching indeterminate stuff.

	$indeterminate       = isset( $_REQUEST['indeterminate_post_category'] ) ? $_REQUEST['indeterminate_post_category'] : array();
	$selected_categories = $_REQUEST['post_category']; // Take these for what it's worth

	$terms = array_unique( array_merge( array_intersect( $old_tt_ids, $indeterminate ), array_diff( $selected_categories, $indeterminate ) ) );
	// Yes, sorry for this long line, but I only had 3 hours at WCEU2020 Contributor Day.
	// But we basically make sure that we bring back anything in $old_tt_ids that was indeterminate.
	// And also makre sure that $tt_ids that are indeterminate were not added (in case they were indeterminate but checked, due to JS reasons).

	// Prevent an infinite loop by unregistering ourselves for the next call.
	remove_action( 'set_object_terms', $closure );

	wp_set_post_categories( $object_id, $terms );

	add_action( 'set_object_terms', $closure, 10, 6 );
}, 10, 6 );
