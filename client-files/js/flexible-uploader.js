var FlexibleUploaderJS = (function(scope) {
	var addEvent = function( obj, type, fn ) {
		if (obj.addEventListener)
			obj.addEventListener(type, fn, false);
		else if (obj.attachEvent)
			obj.attachEvent('on' + type, function() { return fn.call(obj, window.event);});
	},

	d = document,

	/**
	 * Determine whether the given element has an element 
	 * in its ancestory with the given class name
	 *
	 * @param DOMElement el The element in question.
	 * @param string The name of the class to check for.
	 * @param DOMElement (Optional) The root at which no 
	 * farther to check.  Default is documentElement.
	 * @return DOMElement|false The element with that
	 * class or false if none found.
	 */
	ancestryHasClass = function( el, theClass, root ) {
		if ( ! root )
			root = document;
		var re = new RegExp('\\b' + theClass + '\\b');

		do {
			if ( el.className && re.exec( el.className ) )
				return el;

			if ( el.parentNode )
				el = el.parentNode;
		} while ( el && el != root );

		return false;
	},


	adminSetup = function() {
		var body = d.getElementsByTagName('body')[0],
		form = d.getElementById( flexibleUploaderFormId );
		if ( form && body ) {
			form.style.display = 'none';
			body.appendChild( form );

			addEvent( d, 'click', eventClickAdmin );
		}
	},

	eventClickAdmin = function(e) {
		var target = e.target || e.srcElement;

		if ( ancestryHasClass( target, 'launch-flexible-uploader-lightbox' ) ) {
			alert( 'hi there' );

			if ( e.stopPropagation )
				e.stopPropagation();
			if ( e.preventDefault )
				e.preventDefault();
			e.cancelBubble = true;
			e.returnValue = false;
			return false;
		}

	},

	uploaderSetup = function() {
		if ( 'undefined' == typeof flexibleUploader )
			return;

		var button = d.createElement('a'),
		container = d.getElementById( flexibleUploaderContainerId ),
		progressBar,
		progWrap,
		up = flexibleUploader,

		progressUpdater = function(up, file, resp) {
			progressBar = d.getElementById( flexibleUploaderProgressBarId );
			progWrap = d.getElementById( flexibleUploaderProgressBarWrap );

			if ( ! file.size )
				return;
			if ( ! resp )
				resp = {};

			var perc = 100 * ( file.loaded / file.size );

			if ( progressBar ) {
				progressBar.style.width = perc + '%';	
				progressBar.innerHTML = Math.floor( perc ) + '%';
			}
			
			// All files are uploaded
			if (up.total.uploaded == up.files.length) {
				up.stop();

				if ( progWrap ) {
					if ( ! progWrap.className || -1 === progWrap.className.indexOf('active') ) {
						progWrap.className = 'inactive';
					} else {
						progWrap.className = progWrap.className.replace(/\bactive/g, 'inactive'); 
					}
				}
			}
		};

		if ( progressBar )
			progressBar.style.width = '0%';

		button.className = 'button';
		button.href = '#';
		button.id = flexibleUploaderBrowseButtonId;
		button.innerHTML = 'Select Image';

		up.bind('StateChanged', function() {
			var progWrap = d.getElementById( flexibleUploaderProgressBarWrap );

			if ( up.state == plupload.STARTED ) {
				if ( progWrap && progWrap.className ) {
					progWrap.className = progWrap.className.replace(/inactive/g, '');
				}
			} else {
				if ( progWrap && progWrap.className ) {
					progWrap.className += ' inactive';
				}
			}
		});

		up.bind('FileUploaded', progressUpdater);
		up.bind('UploadProgress', progressUpdater);
		
		up.bind('Init', function() {
			var form = d.getElementById( flexibleUploaderFormId ),
			progressBar = d.getElementById( flexibleUploaderProgressBarId ),
			progWrap = d.getElementById( flexibleUploaderProgressBarWrap );

			if ( progressBar )
				progressBar.style.width = '0%';
				
			addEvent( form, 'submit', function(e) {
				up.start();
			
				if ( e.stopPropagation )
					e.stopPropagation();
				if ( e.preventDefault )
					e.preventDefault();
				e.cancelBubble = true;
				e.returnValue = false;
				return false;
			} );

		});
		/*

		up.bind('Error', function(up, err) {
console.log(err);
		});
		*/
		
		if ( container  )
			up.init();
	},

	initialized = false,
	init = function() {
		if ( initialized )
			return;
		initialized = true;

		uploaderSetup();		

		if ( 'undefined' != typeof flexibleUploaderIsAdmin ) {
			adminSetup();
		}
	}

	addEvent(d, 'DOMContentLoaded', init);
	addEvent(window, 'load', init);
})(this);
