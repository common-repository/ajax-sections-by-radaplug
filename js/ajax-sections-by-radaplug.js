var radaplug_scan_section;
var radaplug_ajax_section;
var radaplug_motion_effects;
var radaplug_loading_svg;

jQuery(document).ready(function ($) {

    radaplug_scan_section(0);

});

(function ($) {

    var radaplug_post_dup = 1;
    var radaplug_ajax_alt = '';
    var radaplug_ajax_sects = 0;
    var radaplug_ajax_times = true;
    var radaplug_jqxhr = [];

    radaplug_scan_section = function (post_id) {

        radaplug_ajax_sects = 0;

        $(".radaplug-ajax-section").each(function () {

            if (typeof $(this).attr("id") != "undefined" && $(this).attr("_page_builder") != "gutenberg") {
                return true;
            } else if ($(this).attr("_page_builder") == "gutenberg") {
                $(this).parent('div.wp-block-radaplug-ajax-sections').css('color', $('button.editor-document-tools__inserter-toggle').css('color'));
                $(this).parent('div.wp-block-radaplug-ajax-sections').css('background', $('button.editor-document-tools__inserter-toggle').css('background'));
                $(this).parent('div.wp-block-radaplug-ajax-sections').css('font-weight', 'normal');
                $(this).parent('div.wp-block-radaplug-ajax-sections').css('padding', '5px');
                $(this).css('background', $(this).closest('div.editor-styles-wrapper').css('background'));
                $(this).css('color', $(this).closest('div.editor-styles-wrapper').css('color'));
                $(this).css('font-weight', $(this).closest('div.editor-styles-wrapper').css('font-weight'));
                $(this).css('padding', '10px');
            }

            var post = 0;
            var type = '';
            var delay = 0;
            var button = '';
            var spinner = '';

            radaplug_ajax_sects++;
            if (radaplug_ajax_sects > 1) {
                $(this).parent().find('div#radaplug-message-div').text('Get Pro version to load more than one Ajax Section at frontend');
            } else {
                $(this).parent().find('div#radaplug-message-div').text('');
            }

            post = $(this).attr("post");
            if (typeof post == "undefined" || post == "undefined" || post == "" || post <= 0 || (post_id > 0 && post != post_id)) {
                if ($(this).attr("_page_builder") == "gutenberg" && post == "") {
                    $(this).html("Please set POST ID or Slug ...").show();
                }
                return true;
            }

            radaplug_post_dup = 1;

            $(".radaplug-ajax-section[post=" + post + "]").each(function () {

                var attributes = [];

                var _page_builder = $(this).attr("_page_builder");

                var _load_via_ajax = $(this).attr("_load_via_ajax");
                attributes.push(_load_via_ajax);
                if (_load_via_ajax == 'false' || _load_via_ajax == 'no') {
                    $(this).attr("delay", "0");
                    $(this).attr("button", "");
                    $(this).attr("spinner", "no");
                    $(this).attr("_entrance_animation", "show");
                    $(this).attr("_animation_duration", "0");
                }

                type = $(this).attr("type");
                attributes.push(type);

                delay = $(this).attr("delay");
                attributes.push(delay);
                if (typeof delay == "undefined" || delay == "undefined" || delay <= 0) {
                    delay = 0;
                }

                button = $(this).attr("button");
                attributes.push(button);
                if (typeof button != "undefined" && button != 'undefined' && button != '') {
                    delay = 0;
                } else {
                    button = '';
                }

                spinner = $(this).attr("spinner");
                attributes.push(spinner);

                if ($(this).attr("id") == "radaplug-ajax-section-" + post && $(this).attr("attributes") == attributes.join('|')) {
                    radaplug_post_dup++;
                    return true;
                }

                if (post == post_id) {
                    $(this).html('&nbsp;');
                    radaplug_ajax_alt = 'Post content is not available, please set a correct POST ID or Slug ...';
                    radaplug_ajax_times = true;
                }

                $(this).attr("id", "radaplug-ajax-section-" + post);
                $(this).attr("attributes", attributes.join('|'));

                var current_post_max = 0;
                var current_post_dup = [];
                $("div[id=radaplug-ajax-section-" + post + "]").each(function () {
                    if (typeof $(this).attr("dup") != "undefined") {
                        current_post_dup.push($(this).attr("dup"));
                        current_post_max = Math.max(current_post_max, $(this).attr("dup"));
                    }
                });
                if ($.inArray($(this).attr("dup"), current_post_dup) == -1) {
                    if (current_post_max > 0) {
                        radaplug_post_dup = current_post_max + 1;
                    }
                }
                if (typeof $(this).attr("dup") == "undefined") {
                    $(this).attr("dup", radaplug_post_dup);
                    radaplug_post_dup++;
                }

                if (typeof $(this).attr("_page_builder") == "undefined") {
                    if (typeof _load_via_ajax == "undefined" || (!_load_via_ajax || _load_via_ajax == 'no')) {
                        return true;
                    }
                }

                const dup = $(this).attr('dup');
                $(this).css("text-align", "center");
                if (typeof button == "undefined" || button == 'undefined' || button == '') {
                    radaplug_ajax_section(post, dup, type, delay);
                } else {
                    // TODO: More options in future versions to style button
                    $(this).html("<button id=radaplug-ajax-button-" + post + " dup=" + dup + ">" + button + "</button>");
                    $("button[id=radaplug-ajax-button-" + post + "][dup=" + dup + "]").click(function () {
                        radaplug_ajax_section(post, dup, type, delay);
                    });
                }

                return radaplug_ajax_times;

            });

        });

    };

    radaplug_ajax_section = function (post, dup, type, delay, CallBack) {

        if (radaplug_ajax_times) {
            radaplug_ajax_times = false;
        } else if (dup != 0 || delay != 0) {
            return;
        }

        var _this;
        var ajax_index;
        if (dup == 0 && delay == 0) {
            if ($("div#radaplug-ajax-section-0").length == 0) {
                $("body").append("<div id=radaplug-ajax-section-0 style=display:none;></div>");
            }
            _this = $("div[id=radaplug-ajax-section-0");
            ajax_index = dup;
        } else {
            _this = $("div[id=radaplug-ajax-section-" + post + "][dup=" + dup + "]");
            ajax_index = post + '|' + dup;
        }

        if (_this.attr('progressing') == 'yes') {
            if (radaplug_jqxhr[ajax_index] != null) {
                radaplug_jqxhr[ajax_index].abort();
            }
        }

        setTimeout(function () {

            _this.attr('progressing', 'yes');

            spinner = _this.attr('spinner');
            if (typeof spinner != "undefined" && spinner != "undefined" && spinner == 'yes') {
                _this.html("<img src='data:image/svg+xml;base64," + radaplug_loading_svg(1, 100, 100) + "'>");
            } else {
                _this.html("&nbsp;");
            }

            radaplug_jqxhr[ajax_index] = $.ajax({
                    url: radaplug_ajax_var.ajaxurl,
                    method: 'POST',
                    global: true,
                    data: {
                        action: 'radaplug_ajax_section',
                        post: post,
                        dup: dup,
                        type: type,
                        nonce: radaplug_ajax_var.nonce,
                    },
                    beforeSend: function (xhr) {},
                })
                .done(function (response, textStatus, jqXHR) {
                    if (textStatus == 'success') {
                        response = JSON.parse(response.data);
                        if (typeof CallBack === 'function') {
                            CallBack(response);
                        } else {
                            const _this = $("div[id=radaplug-ajax-section-" + response.post + "][dup=" + response.dup + "]");
                            const post = _this.attr('post');
                            const type = _this.attr('type');
                            const dup = _this.attr('dup');
                            _this.css("text-align", "inherit").hide();
                            if (response.content != '' && post == response.post && type == response.type && dup == response.dup) {
                                radaplug_motion_effects(_this, response);
                                radaplug_scan_section(0);
                            } else {
                                _this.html(radaplug_ajax_alt).show();
                            }
                        }
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {})
                .always(function (jqXHR, textStatus) {
                    _this.attr('progressing', 'no');
                    radaplug_jqxhr[post + '|' + dup] = null;
                });

        }, delay);

    }

    radaplug_motion_effects = function (_this, response) {

        _this.html(response.content).show();

    }

    radaplug_loading_svg = function (svg, width, height) {

        switch (svg) {
            case 1:
                var loading_svg = '<svg id="ftg_svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><rect id="r" x="25" y="-5" height="10" width="37.5" rx="10" fill="#0000ff"><animate attributeName="fill" attributeType="XML" values="#0000ff;#00ffff;#00ff00;#ffff00;#ff0000;#ff00ff;#0000ff;" begin="0s" dur="6s" calcMode="paced" fill="freeze" repeatCount="indefinite"/></rect><g id="g"><animateTransform attributeName="transform" begin="0s" dur="1s" type="rotate" values="330;300;270;240;210;180;150;120;90;60;30" repeatCount="indefinite" calcMode="discrete"/><use xlink:href="#r" opacity="1"/><use xlink:href="#r" opacity=".9"  transform="rotate(30)  scale(0.95)" /><use xlink:href="#r" opacity=".8"  transform="rotate(60)  scale(0.9)"  /><use xlink:href="#r" opacity=".7"  transform="rotate(90)  scale(0.85)" /><use xlink:href="#r" opacity=".6"  transform="rotate(120) scale(0.8)"  /><use xlink:href="#r" opacity=".5"  transform="rotate(150) scale(0.75)" /><use xlink:href="#r" opacity=".4"  transform="rotate(180) scale(0.7) " /><use xlink:href="#r" opacity=".35"  transform="rotate(210) scale(0.65)" /><use xlink:href="#r" opacity=".3"  transform="rotate(240) scale(0.6) " /><use xlink:href="#r" opacity=".25" transform="rotate(270) scale(0.55)" /><use xlink:href="#r" opacity=".2"  transform="rotate(300) scale(0.5)"  /><use xlink:href="#r" opacity=".15" transform="rotate(330) scale(0.45)" /></g></defs><use id="loader" xlink:href="#g" transform="translate(150,150)"></use></svg>';
                var loading_div = document.createElement('div');
                var loading_xml;
                loading_div.innerHTML = loading_svg.trim();
                loading_svg = loading_div.firstChild;
                loading_svg.setAttribute("width", width);
                loading_svg.setAttribute("height", height);
                loading_xml = btoa((new XMLSerializer()).serializeToString(loading_svg));
                break;
        }

        return loading_xml;

    }

    $.fn.radaplug_insert_section = function (SectionLocation, SectionParams, CallBack) {

        SectionDiv = "<div id='radaplug-message-div'></div><div class='radaplug-ajax-section' _page_builder='jQuery'";

        if (typeof SectionParams['post'] == 'undefined' || SectionParams['post'] == '') {
            SectionDiv += " post=''";
        } else {
            SectionDiv += " post=" + SectionParams['post'];
        }

        if (typeof SectionParams['type'] == 'undefined' || SectionParams['type'] == '') {
            SectionDiv += " type=''";
        } else {
            SectionDiv += " type=" + SectionParams['type'];
        }

        if (typeof SectionParams['delay'] == 'undefined' || SectionParams['delay'] == '') {
            SectionDiv += " delay=''";
        } else {
            SectionDiv += " delay=" + SectionParams['delay'];
        }

        if (typeof SectionParams['button'] == 'undefined' || SectionParams['button'] == '') {
            SectionDiv += " button=''";
        } else {
            SectionDiv += " button=" + SectionParams['button'];
        }

        if (typeof SectionParams['spinner'] == 'undefined' || SectionParams['spinner'] == '') {
            SectionDiv += " spinner=''";
        } else {
            SectionDiv += " spinner=" + SectionParams['spinner'];
        }

        SectionDiv += "></div>";

        switch (SectionLocation) {
            case 'after':
                $(this).after(SectionDiv);
                break;
            case 'before':
                $(this).before(SectionDiv);
                break;
            case 'append':
                $(this).append(SectionDiv);
                break;
            case 'prepend':
                $(this).prepend(SectionDiv);
                break;
            case 'replace':
                $(this).html(SectionDiv);
                break;
        }

        radaplug_scan_section(0);

        if (typeof CallBack == 'function') {
            CallBack();
        }

        return this;

    }

})(jQuery);