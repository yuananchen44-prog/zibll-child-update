(function ($) {
    "use strict";

    var cfg = window.ZibllDemoEmoji || {};
    var groups = $.isArray(cfg.groups) ? cfg.groups : [];
    if (!groups.length) {
        return;
    }

    function hasEmojiPanel() {
        return $(".dropup.relative.smilie > .dropdown-menu > .dropdown-smilie").length > 0;
    }

    var includeDefault = !!cfg.includeDefault;
    var defaultLabel = cfg.defaultLabel || "默认";
    var defaultSmiliesUrl = String(cfg.defaultSmiliesUrl || "").replace(/\/+$/, "");
    var panelWidth = parseInt(cfg.panelWidth, 10) || 360;
    var defaultGroupKey = "__zibll_default__";
    var tokenMap = {};
    var tokenNameMap = {};

    groups.forEach(function (group) {
        if (!group || !group.items || !group.items.length) {
            return;
        }
        group.items.forEach(function (item) {
            if (item && item.token && item.url) {
                tokenMap[item.token] = item.url;
                tokenNameMap[item.token] = item.name || item.token;
            }
        });
    });

    function collectTokenMapFromDom(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find(".dropdown-smilie .smilie-icon").each(function () {
            var $icon = $(this);
            var token = String($icon.attr("data-smilie") || "").trim();
            if (!token) {
                return;
            }

            var $img = $icon.is("img") ? $icon : $icon.find("img").first();
            var url =
                $icon.attr("data-src") ||
                $icon.attr("src") ||
                ($img.length ? ($img.attr("data-src") || $img.attr("src")) : "");

            if (url) {
                tokenMap[token] = url;
                tokenNameMap[token] = tokenNameMap[token] || token;
            }
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function extractTokenFromText(text) {
        if (!text) {
            return "";
        }
        var match = String(text).match(/(zcem_[a-z0-9]{18})/i);
        return match ? match[1] : "";
    }

    function getTokenFromImg($img) {
        return (
            extractTokenFromText($img.attr("data-src")) ||
            extractTokenFromText($img.attr("src")) ||
            extractTokenFromText($img.attr("alt"))
        );
    }

    function repairSmilieImgs(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find("img.smilie-icon").addBack("img.smilie-icon").each(function () {
            var $img = $(this);
            var token = getTokenFromImg($img);
            if (!token || !tokenMap[token]) {
                return;
            }
            var realUrl = tokenMap[token];
            $img.attr("src", realUrl);
            $img.attr("data-src", realUrl);
            $img.attr("alt", "表情[" + (tokenNameMap[token] || token) + "]");
        });
    }

    function getPreviewEmojiData(token) {
        token = String(token || "").trim();
        if (!token || !/^[a-z0-9_-]+$/i.test(token)) {
            return null;
        }

        var mappedToken = tokenMap[token] ? token : token.toLowerCase();
        if (tokenMap[mappedToken]) {
            return {
                url: tokenMap[mappedToken],
                name: tokenNameMap[mappedToken] || token
            };
        }

        if (!defaultSmiliesUrl) {
            return null;
        }

        return {
            url: defaultSmiliesUrl + "/" + encodeURIComponent(token) + ".gif",
            name: token
        };
    }

    function repairPrivatePreviewEmoji(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find(".chat-lists .msg-list dd").addBack(".chat-lists .msg-list dd").each(function () {
            var $preview = $(this);
            var source = $preview.text();
            var pattern = /\[表情:([a-z0-9_-]+)\]/gi;
            var match;
            var lastIndex = 0;
            var replaced = 0;
            var fragment = document.createDocumentFragment();

            while ((match = pattern.exec(source)) !== null) {
                var emoji = getPreviewEmojiData(match[1]);
                if (match.index > lastIndex) {
                    fragment.appendChild(document.createTextNode(source.slice(lastIndex, match.index)));
                }

                if (!emoji) {
                    fragment.appendChild(document.createTextNode(match[0]));
                } else {
                    var image = document.createElement("img");
                    image.className = "smilie-icon zce-private-preview-emoji";
                    image.src = emoji.url;
                    image.alt = "表情[" + emoji.name + "]";
                    image.loading = "lazy";
                    image.setAttribute("data-zce-fallback", match[0]);
                    image.onerror = function () {
                        if (this.parentNode) {
                            this.parentNode.replaceChild(
                                document.createTextNode(this.getAttribute("data-zce-fallback") || "[表情]"),
                                this
                            );
                        }
                    };
                    fragment.appendChild(image);
                    replaced++;
                }
                lastIndex = pattern.lastIndex;
            }

            if (!replaced) {
                return;
            }
            if (lastIndex < source.length) {
                fragment.appendChild(document.createTextNode(source.slice(lastIndex)));
            }

            $preview.empty().append(fragment);
        });
    }

    function repairWithDelayBurst() {
        var delays = [0, 80, 220, 500];
        delays.forEach(function (ms) {
            setTimeout(function () {
                repairSmilieImgs(document);
                repairPrivatePreviewEmoji(document);
            }, ms);
        });
    }

    function captureTextareaStyle(textareaEl) {
        if (!textareaEl) {
            return null;
        }

        var cs = window.getComputedStyle(textareaEl);
        var textColor = cs.color;
        if (!textColor || textColor === "transparent" || /rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*0(?:\.0+)?\s*\)/i.test(textColor)) {
            textColor = window.getComputedStyle(document.body).color || "#333";
        }

        var textareaHeight = parseFloat(cs.height);
        var minHeight = "";
        if (isFinite(textareaHeight) && textareaHeight > 0) {
            minHeight = Math.max(68, Math.round(textareaHeight)) + "px";
        }

        return {
            padding: cs.padding,
            fontFamily: cs.fontFamily,
            fontSize: cs.fontSize,
            fontWeight: cs.fontWeight,
            fontStyle: cs.fontStyle,
            lineHeight: cs.lineHeight,
            letterSpacing: cs.letterSpacing,
            textAlign: cs.textAlign,
            color: textColor,
            border: cs.border,
            borderRadius: cs.borderRadius,
            backgroundColor: cs.backgroundColor,
            minHeight: minHeight
        };
    }

    function syncRichEditorStyle($textarea, $editor) {
        var textareaEl = $textarea[0];
        var editorEl = $editor[0];
        if (!textareaEl || !editorEl) {
            return;
        }

        var snapshot = $textarea.data("zceStyleSnapshot");
        if (!snapshot) {
            snapshot = captureTextareaStyle(textareaEl);
            if (snapshot) {
                $textarea.data("zceStyleSnapshot", snapshot);
            }
        }
        if (snapshot) {
            $editor.css(snapshot);
        }
    }

    function textToHtml(text) {
        if (!text) {
            return "";
        }
        return escapeHtml(text).replace(/\n/g, "<br>");
    }

    function tokensToRichHtml(text) {
        var source = String(text || "");
        if (!source) {
            return "";
        }

        var output = "";
        var lastIndex = 0;
        var re = /\[g=([^\]]+)\]/g;
        var match;

        while ((match = re.exec(source)) !== null) {
            var plainPart = source.slice(lastIndex, match.index);
            if (plainPart) {
                output += textToHtml(plainPart);
            }

            var token = String(match[1] || "").trim();
            var url = tokenMap[token];
            if (url) {
                output += '<img class="zce-inline-emoji" data-zce-token="' + escapeHtml(token) + '" src="' + escapeHtml(url) + '" alt="' + escapeHtml(token) + '">';
            } else {
                output += textToHtml(match[0]);
            }

            lastIndex = re.lastIndex;
        }

        var tail = source.slice(lastIndex);
        if (tail) {
            output += textToHtml(tail);
        }

        return output;
    }

    function readEditorNode(node) {
        if (!node) {
            return "";
        }

        if (node.nodeType === 3) {
            return String(node.nodeValue || "").replace(/\u00a0/g, " ");
        }

        if (node.nodeType !== 1) {
            return "";
        }

        var tag = (node.tagName || "").toUpperCase();
        if (tag === "BR") {
            return "\n";
        }

        if (tag === "IMG" && node.classList && node.classList.contains("zce-inline-emoji")) {
            var token = String(node.getAttribute("data-zce-token") || "").trim();
            if (token) {
                return "[g=" + token + "]";
            }
            return "";
        }

        var text = "";
        var children = node.childNodes || [];
        for (var i = 0; i < children.length; i++) {
            text += readEditorNode(children[i]);
        }

        if (/^(DIV|P|LI)$/.test(tag) && node.nextSibling && text.slice(-1) !== "\n") {
            text += "\n";
        }

        return text;
    }

    function readEditorValue(editorEl) {
        if (!editorEl) {
            return "";
        }
        var text = "";
        var children = editorEl.childNodes || [];
        for (var i = 0; i < children.length; i++) {
            text += readEditorNode(children[i]);
        }
        if (!editorEl.querySelector("img.zce-inline-emoji") && /^\n+$/.test(text)) {
            return "";
        }
        return text;
    }

    function setCaretToEnd(editorEl) {
        if (!editorEl || typeof window.getSelection !== "function") {
            return;
        }
        var selection = window.getSelection();
        if (!selection) {
            return;
        }
        var range = document.createRange();
        range.selectNodeContents(editorEl);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function insertPlainTextAtCaret(text) {
        if (document.queryCommandSupported && document.queryCommandSupported("insertText")) {
            document.execCommand("insertText", false, text);
            return;
        }

        var selection = window.getSelection && window.getSelection();
        if (!selection || !selection.rangeCount) {
            return;
        }
        var range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(document.createTextNode(text));
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function getEditorByTextarea($textarea) {
        return $textarea.closest(".zce-rich-wrap").find(".zce-rich-editor").first();
    }

    function syncEditorFromTextarea($textarea, keepCaret) {
        var $editor = getEditorByTextarea($textarea);
        if (!$editor.length) {
            return;
        }

        var editorEl = $editor[0];
        var hasFocus = document.activeElement === editorEl;
        var sourceValue = String($textarea.val() || "");
        if ($editor.data("zceLastValue") === sourceValue) {
            syncRichEditorStyle($textarea, $editor);
            return;
        }

        $editor.html(tokensToRichHtml(sourceValue));
        $editor.data("zceLastValue", sourceValue);
        syncRichEditorStyle($textarea, $editor);

        if (keepCaret && hasFocus) {
            setCaretToEnd(editorEl);
        }
    }

    function syncTextareaFromEditor($textarea) {
        var $editor = getEditorByTextarea($textarea);
        if (!$editor.length) {
            return;
        }

        var value = readEditorValue($editor[0]);
        $editor.data("zceLastValue", value);
        if (String($textarea.val() || "") === value) {
            return;
        }

        $textarea.data("zceSyncFromEditor", 1);
        $textarea.val(value);
        $textarea.removeData("zceSyncFromEditor");
    }

    function bindRichEditorEvents($textarea, $editor) {
        if ($editor.data("zceRichBound")) {
            return;
        }
        $editor.data("zceRichBound", 1);

        $editor.on("input", function () {
            syncTextareaFromEditor($textarea);
        });

        $editor.on("keyup", function (event) {
            syncTextareaFromEditor($textarea);
            var maybeTokenDone = event && (event.key === "]" || event.keyCode === 221);
            if (maybeTokenDone && /\[g=[^\]]+\]/.test(String($textarea.val() || ""))) {
                syncEditorFromTextarea($textarea, true);
            }
        });

        $editor.on("blur", function () {
            syncTextareaFromEditor($textarea);
            syncEditorFromTextarea($textarea, false);
        });

        $editor.on("keydown", function (event) {
            var enterPressed = event.key === "Enter" || event.keyCode === 13;
            if (event.ctrlKey && enterPressed) {
                var submit = document.getElementById("submit");
                if (submit && typeof submit.click === "function") {
                    submit.click();
                    event.preventDefault();
                }
            }
        });

        $editor.on("paste", function (event) {
            var oe = event.originalEvent || event;
            var clipboard = oe && oe.clipboardData;
            if (!clipboard) {
                return;
            }
            var text = clipboard.getData("text/plain");
            if (typeof text !== "string") {
                return;
            }
            event.preventDefault();
            insertPlainTextAtCaret(text);
            syncTextareaFromEditor($textarea);
        });

        $textarea.on("input change", function () {
            if ($textarea.data("zceSyncFromEditor")) {
                return;
            }
            syncEditorFromTextarea($textarea, false);
        });
    }

    function initRichEditor(context) {
        collectTokenMapFromDom(context);
        var $scope = context ? $(context) : $(document);
        $scope.find("textarea.grin").each(function () {
            var $textarea = $(this);
            if ($textarea.data("zceRichReady")) {
                syncEditorFromTextarea($textarea, false);
                return;
            }

            $textarea.wrap('<div class="zce-rich-wrap"></div>');
            var placeholder = String($textarea.attr("placeholder") || "");
            var $editor = $('<div class="zce-rich-editor" contenteditable="true" role="textbox" aria-multiline="true"></div>');
            if (placeholder) {
                $editor.attr("data-placeholder", placeholder);
            }

            var tabindex = $textarea.attr("tabindex");
            if (tabindex) {
                $editor.attr("tabindex", tabindex);
            }

            $editor.insertBefore($textarea);
            syncRichEditorStyle($textarea, $editor);
            $textarea.addClass("zce-source-textarea").attr("aria-hidden", "true");
            $textarea.data("zceRichReady", 1);

            bindRichEditorEvents($textarea, $editor);
            syncEditorFromTextarea($textarea, false);
        });
    }

    function initRichEditorBurst() {
        var delays = [0, 80, 220, 500];
        delays.forEach(function (ms) {
            setTimeout(function () {
                initRichEditor(document);
            }, ms);
        });
    }

    function getViewportHeight() {
        if (window.visualViewport && window.visualViewport.height) {
            return window.visualViewport.height;
        }
        return window.innerHeight || document.documentElement.clientHeight || 0;
    }

    function normalizeMainMinHeight() {
        var $main = $("main").first();
        if (!$main.length || !$main[0] || !$main[0].style) {
            return;
        }

        var inlineMinHeight = parseFloat($main[0].style.minHeight || "");
        if (!isFinite(inlineMinHeight) || inlineMinHeight <= 0) {
            return;
        }

        var viewportHeight = getViewportHeight();
        if (!viewportHeight) {
            return;
        }

        // 主题正常场景下 main 的最小高度不会远超视口；超出则视为异常值（如 3000+px）
        if (inlineMinHeight > viewportHeight + 260) {
            $main.css("min-height", "");

            var docHeight = Math.max(
                document.body ? document.body.scrollHeight : 0,
                document.documentElement ? document.documentElement.scrollHeight : 0
            );

            // 页面内容确实不足一屏时，回写一个安全值，避免底部贴边抖动
            if (docHeight < viewportHeight) {
                var headerHeight = $(".header:visible").first().outerHeight() || 0;
                var footerHeight = $(".footer:visible").first().outerHeight() || 0;
                var safeMinHeight = Math.max(0, Math.round(viewportHeight - headerHeight - footerHeight - 20));
                if (safeMinHeight > 0) {
                    $main.css("min-height", safeMinHeight + "px");
                }
            }
        }
    }

    function normalizeMainMinHeightBurst() {
        var delays = [0, 120, 360, 900, 1800];
        delays.forEach(function (ms) {
            setTimeout(normalizeMainMinHeight, ms);
        });
    }

    function hookThemeAutoMaxHeight() {
        if (window.__zibllEmojiAutoHeightHooked) {
            return;
        }
        if (typeof window.auto_maxHeight !== "function") {
            return;
        }

        var originalAutoMaxHeight = window.auto_maxHeight;
        window.auto_maxHeight = function () {
            var result;
            try {
                result = originalAutoMaxHeight.apply(this, arguments);
            } catch (e) {
                result = undefined;
            }
            normalizeMainMinHeightBurst();
            return result;
        };

        window.__zibllEmojiAutoHeightHooked = true;
    }

    function watchCommentList() {
        if (typeof MutationObserver === "undefined") {
            return;
        }
        var root = document.body;
        if (!root || root.__zibllEmojiObserverReady) {
            return;
        }
        root.__zibllEmojiObserverReady = true;

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (!mutation.addedNodes || !mutation.addedNodes.length) {
                    return;
                }
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }
                    repairSmilieImgs(node);
                    repairPrivatePreviewEmoji(node);
                });
            });
        });

        observer.observe(root, { childList: true, subtree: true });
    }

    function createItem(item, groupId) {
        var $a = $('<a class="smilie-icon zibll-demo-item" href="javascript:;"></a>');
        $a.attr("data-smilie", item.token);
        $a.attr("data-zibll-group", groupId);
        $a.css("display", "none");
        if (item.name) {
            $a.attr("title", item.name);
        }

        var $img = $("<img>");
        $img.attr("src", item.url);
        $img.attr("alt", "[" + (item.name || "") + "]");
        $a.append($img);
        return $a;
    }

    function showGroup($panel, $tabWrap, groupId) {
        $panel.find(".zibll-demo-item").hide();
        $panel.find('.zibll-demo-item[data-zibll-group="' + groupId + '"]').css("display", "inline-block");

        $tabWrap.find(".zibll-demo-tab").removeClass("active");
        $tabWrap.find('.zibll-demo-tab[data-zibll-tab="' + groupId + '"]').addClass("active");
    }

    function buildTabs($container, $panel) {
        var tabs = [];
        var $defaultIcons = $panel.find("a.smilie-icon:not(.zibll-demo-item)");

        $defaultIcons.each(function () {
            $(this).addClass("zibll-demo-item").attr("data-zibll-group", defaultGroupKey).css("display", "none");
        });

        if (includeDefault && $defaultIcons.length) {
            tabs.push({
                id: defaultGroupKey,
                title: defaultLabel
            });
        }

        groups.forEach(function (group) {
            if (!group || !group.items || !group.items.length) {
                return;
            }

            group.items.forEach(function (item) {
                $panel.append(createItem(item, group.id));
            });

            tabs.push({
                id: group.id,
                title: group.title || group.id
            });
        });

        if (!tabs.length) {
            return;
        }

        var $tabWrap = $('<div class="zibll-demo-tabs mini-scrollbar" role="tablist"></div>');
        var panelRealWidth = Math.ceil($panel.outerWidth()) || parseInt($panel.css("width"), 10) || 260;
        $tabWrap.css({
            width: panelRealWidth + "px",
            maxWidth: panelRealWidth + "px"
        });

        tabs.forEach(function (tab, index) {
            var $tab = $('<button type="button" class="but c-blue pw-1em zibll-demo-tab"></button>');
            $tab.attr("data-zibll-tab", tab.id).text(tab.title);
            if (index === 0) {
                $tab.addClass("active");
            }
            $tabWrap.append($tab);
        });

        $panel.after($tabWrap);
        showGroup($panel, $tabWrap, tabs[0].id);

        $tabWrap.on("click", ".zibll-demo-tab", function (e) {
            e.preventDefault();
            showGroup($panel, $tabWrap, $(this).attr("data-zibll-tab"));

            if (typeof this.scrollIntoView === "function") {
                this.scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                    inline: "center"
                });
            }
        });

        // 将鼠标滚轮垂直滚动映射为分组栏横向滚动
        $tabWrap.on("wheel", function (e) {
            var oe = e.originalEvent;
            if (!oe) {
                return;
            }

            var deltaX = oe.deltaX || 0;
            var deltaY = oe.deltaY || 0;
            if (Math.abs(deltaY) > Math.abs(deltaX)) {
                this.scrollLeft += deltaY;
                e.preventDefault();
            }
        });

        $container.data("zibll-demo-ready", 1).addClass("zibll-demo-ready");
    }

    function initContainer($container) {
        if ($container.data("zibll-demo-ready")) {
            return;
        }

        var $panel = $container.find("> .dropdown-menu > .dropdown-smilie").first();
        if (!$panel.length) {
            return;
        }

        collectTokenMapFromDom($container);
        $panel.css("max-width", panelWidth + "px");
        buildTabs($container, $panel);
    }

    function initEmojiPanel(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find(".dropup.relative.smilie").each(function () {
            initContainer($(this));
        });
        collectTokenMapFromDom(context);
    }

    $(function () {
        collectTokenMapFromDom(document);
        repairSmilieImgs(document);
        repairPrivatePreviewEmoji(document);
        watchCommentList();
        if (!hasEmojiPanel()) {
            return;
        }

        hookThemeAutoMaxHeight();
        initEmojiPanel(document);
        initRichEditorBurst();
        normalizeMainMinHeightBurst();
    });

    $(document).on("zib_ajax.success", function () {
        repairWithDelayBurst();
        watchCommentList();
        if (!hasEmojiPanel()) {
            return;
        }

        initEmojiPanel(document);
        initRichEditorBurst();
        normalizeMainMinHeightBurst();
    });

    $(document).on("loaded.bs.modal post_ajax.ed", function () {
        setTimeout(function () {
            initEmojiPanel(document);
            initRichEditorBurst();
            repairWithDelayBurst();
        }, 0);
    });

    $(document).on("zib_ajax.success", ".send-private", function () {
        var $textarea = $(this).closest("form").find("textarea.grin").first();
        setTimeout(function () {
            syncEditorFromTextarea($textarea, false);
            repairSmilieImgs(document);
        }, 0);
    });

    $(document).on("zib_ajax.success", "#commentform #submit", function () {
        repairWithDelayBurst();
    });

    $(document).on("auto_fun", function () {
        if (hasEmojiPanel()) {
            normalizeMainMinHeightBurst();
        }
    });

    $(window).on("load resize orientationchange", function () {
        if (!hasEmojiPanel()) {
            return;
        }

        hookThemeAutoMaxHeight();
        initRichEditorBurst();
        normalizeMainMinHeightBurst();
    });

    $(document).on("click", ".dropdown-smilie .smilie-icon", function () {
        setTimeout(function () {
            initRichEditor(document);
            $("textarea.grin").each(function () {
                syncEditorFromTextarea($(this), true);
            });
        }, 0);
    });
})(jQuery);
