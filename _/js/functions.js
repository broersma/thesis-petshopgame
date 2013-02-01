// remap jQuery to $
(function($){})(window.jQuery);


String.prototype.endsWith = function (suffix) {
    "use strict";

    return this.indexOf(suffix, this.length - suffix.length) !== -1;
};

Array.prototype.shuffle = function () {
  var i, j, t;
  for (i = 1; i < this.length; i++) {
    j = Math.floor(Math.random()*(1+i));  // choose j in [0..i]
    if (j != i) {
      t = this[i];                        // swap list[i] and list[j]
      this[i] = this[j];
      this[j] = t;
    }
  }
}

// Post an action to the server.
function post_action(action, params, callback) {
    "use strict";

    // Defaults arguments.
    params = typeof params !== 'undefined' ? params : {};
    callback = typeof callback !== 'undefined' ? callback : null;

    // Add action to the params for the call to post.
    params.action = action;
    $.post("action.php", params, callback);
}

// Creates a Long polling closure.
function _get_events(callback) {
    "use strict";

    return function() {
        $.getJSON("events.php", function (data) {
            if ( data.length > 0 ) {
                callback(data);
            }
            setTimeout(_get_events(callback), 2000);
        }).error(function () {
            setTimeout(_get_events(callback), 2000);
        });
    };
}

// Used to cleanly call the long polling closure.
function get_events(callback) {
    _get_events(callback)();
}
