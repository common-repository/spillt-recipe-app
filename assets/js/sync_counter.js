/**
 * AJAX sync recipes counter JS.
 */
(function ($) {
	$(document).ready( function(){
		$('#close-manually-sync').on('click', function(){
			$('#manual_sync_result_wrap').removeClass('show');
			location.reload();
		});
		$('#manually-sync').click('on', function(){
			$.ajax({
				url: ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'sync_counter',
					init_counter: '',
				},
				dataType : 'json',
				beforeSend : function ( xhr ) {
					$('#manual_sync_result_wrap').addClass('show');
					$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="do-not-close" class="notice notice-warning"><p>Please, do not close your browser, until all recipes are completed.</p></div>');
				},
				success: function( response ){
					// Prevent repeated sync when 'Manually Sync' button already clicked.
					if( $('#counter-recipes').length ){
						return;
					}

					// If recipes for sync more than 0.
					if( response.length > 0 ){
						$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="counter-recipes" class="notice notice-warning"><p><span id="updated-recipes">0</span> of <span id="all-recipes">'+ response.length +'</span> recipes updated.<span class="spinner is-active"></span></p></div>' );

						$( response ).each(function() {
							let recipe = this;
							$.ajax({
								url: ajax_object.ajax_url,
								type: 'POST',
								data: {
									action: 'sync_counter',
									sync_recipe: '',
									id_recipe: this.ID,
								},
								dataType : 'json',
								beforeSend : function () {
									$('#manual_sync_result_wrap').addClass('show');
								},
								success:function (data) {
									console.log('success');
									console.log(data);
									$('#manually-sync-result tbody').append('<tr><td>'+recipe.post_title+'</td><td>'+data.recipe[0].message+'</td></tr>');/*<td>'+data.recipe[0].code+'</td>*/
								},
								error:function (data) {
									console.log('error');
									console.log(data);
								},
								complete: function(data){
									console.log( typeof data );
									console.log(data);
									$('#updated-recipes').html( parseInt($('#updated-recipes').html()) +1 );

									if( parseInt($('#updated-recipes').html()) === parseInt($('#all-recipes').html()) ){
										$('#counter-recipes').removeClass('notice-warning');
										$('#counter-recipes').addClass('notice-success');
										$(".spinner").removeClass("is-active");
										$('#manually-sync-result').addClass('show');
										$('#do-not-close').remove();
										$('#manual_sync_result_wrap .popup h2').after('<div style="position:absolute;top:20px;right:30px;"><a href="javascript:void(0);" id="close-manually-sync" class="button button-primary">Close</a></div>')
									}
								},
							});
						});
					} else {
						$('#manual_sync_result_wrap .popup').append('<div class="notice notice-error"><p class="error notice-warning">No Recipes Found for sync.</p></div>');
						setTimeout(function() {
							$('#manual_sync_result_wrap').removeClass('show');
						}, 5000);
					}
				},
			});

		});
		$('#spillt-app-recipe-listing').on('submit', function(){
			let action = $('select[name="action"]').val();
			if ( '-1' === action ){
				alert('Need to select action.');
			}
			else {
				let modalTitle = $('select[name="action"] option:selected').text();
				let recipeNumber = $('input:checkbox[name="bulk-spillt-recipe[]"]:checked').length;
				let checkIDs = [];
				$.each($('input:checkbox[name="bulk-spillt-recipe[]"]:checked'), function (index, elem) {
					checkIDs.push($(elem).val());
				});
				$.ajax({
					url: ajax_object.ajax_url,
					type: 'POST',
					data: {
						action: 'spillt_bulk_actions',
						init_counter: '',
						recipe_IDs: checkIDs,
					},
					dataType : 'json',
					beforeSend : function ( xhr ) {
						$('#manual_sync_result_wrap').addClass('show');
						$('#manual_sync_result_wrap h2').text('Bulk ' + modalTitle);
						$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="do-not-close" class="notice notice-warning"><p>Please, do not close your browser, until all recipes are completed.</p></div>');
					},
					success: function( response ){
						// Prevent repeated sync when 'Manually Sync' button already clicked.
						if( $('#counter-recipes').length ){
							return;
						}

						// If recipes for sync more than 0.
						if( response.length > 0 ){
							$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="counter-recipes" class="notice notice-warning"><p><span id="updated-recipes">0</span> of <span id="all-recipes">'+ response.length +'</span> recipes updated.<span class="spinner is-active"></span></p></div>' );


							$( response ).each(function() {
								let recipe = this;
								$.ajax({
									url: ajax_object.ajax_url,
									type: 'POST',
									data: {
										action: 'spillt_bulk_actions',
										sync_recipe: action,
										id_recipe: this.ID,
									},
									dataType : 'json',
									beforeSend : function () {
										$('#manual_sync_result_wrap').addClass('show');
									},
									success:function (data) {
										console.log('success');
										console.log(data);
										$('#manually-sync-result tbody').append('<tr><td>'+recipe.post_title+'</td><td>'+data.recipe[0].message+'</td></tr>'); /*<td>'+data.recipe[0].code+'</td>*/
									},
									error:function (data) {
										console.log('error');
										console.log(data);
									},
									complete: function(data){
										console.log('complete');
										console.log(data);
										$('#updated-recipes').html( parseInt($('#updated-recipes').html()) +1 );

										if( parseInt($('#updated-recipes').html()) === parseInt($('#all-recipes').html()) ){
											$('#counter-recipes').removeClass('notice-warning');
											$('#counter-recipes').addClass('notice-success');
											$(".spinner").removeClass("is-active");
											$('#manually-sync-result').addClass('show');
											$('#do-not-close').remove();
											$('#manual_sync_result_wrap .popup h2').after('<div style="position:absolute;top:20px;right:30px;"><a href="javascript:void(0);" id="close-manually-sync" class="button button-primary">Close</a></div>')
										}
									},
								});
							});
						} else {
							$('#manual_sync_result_wrap .popup').append('<div class="notice notice-error"><p class="error notice-warning">No Recipes Found for sync.</p></div>');
							setTimeout(function() {
								$('#manual_sync_result_wrap').removeClass('show');
							}, 5000);
						}
					},
				});
			}
			return false;
		});

		$('#manually-sync-back').click('on', function(){
			$.ajax({
				url: ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'manually_background',
					init_sync: '',
				},
				dataType : 'json',
				beforeSend : function ( xhr ) {
					$('#manual_sync_result_wrap').addClass('show');
					$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="do-not-close" class="notice notice-warning"><p>Please do not close your browser until all recipes are completed.</p></div>');
				},
				success: function( response ){
					if (response.status === '200'){
						$('#do-not-close').remove();
						$('#manual_sync_result_wrap .popup h2').after('<div style="position:absolute;top:20px;right:30px;"><a href="javascript:void(0);" id="close-manually-sync" class="button button-primary">Close</a></div>')
						$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="do-not-close" class="notice notice-success"><p>' + response.msg + '</p></div>');
					} else {
						$('#do-not-close').remove();
						$('#manual_sync_result_wrap .popup h2').after('<div style="position:absolute;top:20px;right:30px;"><a href="javascript:void(0);" id="close-manually-sync" class="button button-primary">Close</a></div>')
						$('#manual_sync_result_wrap .popup #manually-sync-result').before('<div id="do-not-close" class="notice notice-error"><p>' + response.msg + '</p></div>');
					}
				},
			});
			return false;
		});


	} );
	$( document ).ajaxComplete(function() {
		$('#close-manually-sync').on('click', function(){
			$('#manual_sync_result_wrap').removeClass('show');
			location.reload();
		});
	});
})(jQuery)
