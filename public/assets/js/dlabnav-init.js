"use strict";

var dezSettingsOptions = {};

function getUrlParams(dParam) {
    var dPageURL = window.location.search.substring(1),
        dURLVariables = dPageURL.split('&'),
        dParameterName,
        i;

    for (i = 0; i < dURLVariables.length; i++) {
        dParameterName = dURLVariables[i].split('=');

        if (dParameterName[0] === dParam) {
            return dParameterName[1] === undefined ? true : decodeURIComponent(dParameterName[1]);
        }
    }
}

(function($) {
    "use strict";

    // Uncomment and adjust the RTL logic if needed
    var direction = getUrlParams('dir');
    if (direction === 'rtl') {
        dezSettingsOptions.direction = 'rtl';
    } else {
        dezSettingsOptions.direction = 'ltr';
    }

    dezSettingsOptions = {
        typography: "cairo",
        version: "light",
        layout: "vertical",
        primary: "color_1",
        navheaderBg: "color_1",
        sidebarBg: "color_1",
        sidebarStyle: "full",
        sidebarPosition: "fixed",
        headerPosition: "fixed",
        containerLayout: "full",
    };

    // Assuming `dezSettings` is a valid constructor/function, otherwise make sure it's defined
    if (typeof dezSettings !== 'undefined') {
        new dezSettings(dezSettingsOptions);
    } else {
        console.error("dezSettings function is not defined.");
    }

    jQuery(window).on('resize', function() {
        // Check for the existence of #container_layout and update container layout
        var containerLayout = $('#container_layout').val();
        if (containerLayout) {
            dezSettingsOptions.containerLayout = containerLayout;
        }

        // Re-initialize settings on resize (only if dezSettings is defined)
        if (typeof dezSettings !== 'undefined') {
            new dezSettings(dezSettingsOptions);
        } else {
            console.error("dezSettings function is not defined.");
        }
    });

})(jQuery);
