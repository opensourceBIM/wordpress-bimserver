WordPressBimserver = function() {};
WordPressBimserver = WordPressBimserver.prototype = function() {};

jQuery( document ).ready( function() {
	if( typeof wpBimserverSettings !== "undefined" ) {
		WordPressBimserver.settings = wpBimserverSettings;
		var progressBar = jQuery( "#bimserver-progress-bar" );
		if( progressBar.length > 0 ) {
			var totalWidth = progressBar.width();
			var progress = 0;
			if( WordPressBimserver.settings.initialProgress ) {
				progress = WordPressBimserver.settings.initialProgress;
			}
			progressBar.find( ".filled" ).width( parseInt( totalWidth * progress ) );
			progressBar.find( ".text" ).html( parseInt( progress * 100 ) + "%" );
			setTimeout( WordPressBimserver.updateProgressBar, 1000 );
		}
	}
} );

WordPressBimserver.updateProgressBar = function() {
	jQuery.get( WordPressBimserver.settings.ajaxUrl, { type: "progress" }, function( progress ) {
		if( progress < 0 ) {
			progress = 0;
		}
		var progressBar = jQuery( "#bimserver-progress-bar" );
		if( progressBar.length > 0 ) {
			var totalWidth = progressBar.width();
			progressBar.find( ".filled" ).width( parseInt( totalWidth * progress ) );
			progressBar.find( ".text" ).html( parseInt( progress * 100 ) + "%" );
			if( progress < 1 ) {
				setTimeout( WordPressBimserver.updateProgressBar, 1000 );
			} else {
				setTimeout( function() {
					progressBar.parent().html( WordPressBimserver.settings.completeContent );
				}, 1000 );
			}
		}
	} );
};