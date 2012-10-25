jQuery(document).ready( function($) {
	
	/* Used on the 'Add New Snapshot' panel. Handles the Select All/Deselect All for the tables checkboxes */
	$('a.snapshot-table-select-all').click(function () {
		var link_state = $(this).html();
		if (link_state == "Select all")
		{
			$(this).html('Unselect all');
			$(this).parent().parent().find('ul input:checkbox').attr('checked', true);
		}
		else if (link_state == "Unselect all")
		{
			$(this).html('Select all');
			$(this).parent().parent().find('ul input:checkbox').attr('checked', false);
		}
		
		return false;
	});

	/* Used on the 'All Snapshots' and 'Activity log' panels. Used to show/hide the WP tables container */
	$('a.snapshot-list-table-wp-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-wp-container').is(":visible"))
		{
			var link_state = $(this).html().replace('hide', 'show');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').slideUp();

		} else {
			
			var link_state = $(this).html().replace('show', 'hide');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-wp-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
			
		}
		return false;
	});

	/* Used on the 'All Snapshots' and 'Activity log' panels. Used to show/hide the Non-WP tables container */
	$('a.snapshot-list-table-non-show').click(function () {

		if ($(this).parent().parent().find('p.snapshot-list-table-non-container').is(":visible"))
		{
			var link_state = $(this).html().replace('hide', 'show');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-non-container').slideUp();
			
		} else {

			var link_state = $(this).html().replace('show', 'hide');
			var link_state = $(this).html(link_state);
			$(this).parent().parent().find('p.snapshot-list-table-non-container').slideDown();
			$(this).parent().parent().find('p.snapshot-list-table-non-container').css("background-color", "#FFFF9C").animate({ backgroundColor: "#FFFFFF"}, 1500);
			
		}

		return false;
	});

	/* Used on the 'Add New Snapshot' panel. Handles the form submit to backup one table per request. Seems this was taking too long on some servers. */
	jQuery("form#snapshot-add-new").submit(function() {
		
		/* From the form grab the Name and Notes field values. */
		snapshot_form_name = jQuery('input#snapshot-name', this).val();
		snapshot_form_notes = jQuery('textarea#snapshot-notes', this).val();
		
		/* Build and array of the checked tables to backup */
		var tablesArray = [];
		jQuery('input.snapshot-table-item:checked', this).each( function(){
			var cb_value = jQuery(this).attr('value');
			tablesArray[tablesArray.length] = cb_value;
		});

		// Do we have tables to process?
		if (tablesArray.length > 0)
		{					
			// Clear out the progress text and warning containers 
			jQuery( "#snapshot-warning" ).html();
			jQuery( "#snapshot-warning" ).hide();						


			function snapshot_backup_tables_proc(action, tablesArray, table_idx) {

				if (action == "init") {

					var data = {
						action: 'snapshot_backup_ajax',
						snapshot_action: action
					};

				    jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
				        complete: function(reply_message) {
							if (reply_message.length)
							{
								jQuery( "#snapshot-warning" ).html('<p>'+reply_message+'</p>');
								jQuery( "#snapshot-warning" ).show();
							}
							
							/* Hide the form while processing */
							jQuery('#poststuff').hide();

							// Show the progress bar container.
							jQuery( '#snapshot-progress-bar-container .progress .bar' ).width( '1px' );
							jQuery( '#snapshot-progress-bar-container .progress .percent' ).html( '0%' );
							jQuery( '#snapshot-progress-bar-container' ).show();
											
							snapshot_backup_tables_proc('table', tablesArray, 0);
				        }
				    });
				}
				else if (action == 'finish') {

					var data = {
						action: 'snapshot_backup_ajax',
						snapshot_action: action,
						name: snapshot_form_name,
						notes: snapshot_form_notes,
					};

					jQuery.post(ajaxurl, data, function(reply_message) {
						if (reply_message.length)
						{
							jQuery( "#snapshot-warning" ).html('<p>'+reply_message+'</p>');
							jQuery( "#snapshot-warning" ).show();
						}

					});

				} else if (action == 'table') {

					var table_idx = parseInt(table_idx);						
					var table_count = table_idx+1;
					
					/* If we reached the end of the tables send the finish. */
					if (table_count > tablesArray.length)
					{
						snapshot_backup_tables_proc('finish', tablesArray, 0);
						jQuery( '#snapshot-progress-bar-container' ).hide();
						
						return;
					}

					var data = {
						action: 'snapshot_backup_ajax',
						snapshot_action: action,
						snapshot_table: tablesArray[table_idx]
					};


					/* From the number of tables calculate a percentage for the progress bar */
					var snapshot_percent = Math.ceil((table_count/tablesArray.length)*100);

					// The .bar is set to 200px. So we cheat and just double the percent calculates. shhh.
					jQuery( '#snapshot-progress-bar-container .progress .bar' ).width(snapshot_percent*2+'px');
					jQuery( '#snapshot-progress-bar-container .progress .percent' ).html(snapshot_percent+'%');
					jQuery( '#snapshot-progress-bar-container' ).show();

					/* Write a message to the progress text container shown below the actul bar. Just information what table name and what table count */
					jQuery( "#snapshot-progress-bar-container .snapshot-text" ).html('Archiving: <strong>'+tablesArray[table_idx]+'</strong> ( '+table_count+' / '+tablesArray.length+' )' );

				    jQuery.ajax({
					  	type: 'POST',
					  	url: ajaxurl,
				        data: data,
				        complete: function(reply_message) {
							if (reply_message.length)
							{
								jQuery( "#snapshot-warning" ).html('<p>'+reply_message+'</p>');
								jQuery( "#snapshot-warning" ).show();
							}				
							snapshot_backup_tables_proc('table', tablesArray, table_idx+1);
				        }
				    });
				}
			}
						
			/* Make an AJAX call with 'init' to setup the Session backup filename and other items */
			snapshot_backup_tables_proc('init', tablesArray, 0);
						
		} else {

			/* If the user didn't select any tables show this warning */
			jQuery( "#snapshot-warning" ).html('<p>You must select at least one table</p>');
			jQuery( "#snapshot-warning" ).show();
			
		}
		
		return false;
	});

});
