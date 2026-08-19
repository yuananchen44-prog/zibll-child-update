(function ($) {
    "use strict";

    var cfg = window.ZibllEmojiAdmin || {};
    if (!cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
        return;
    }

    function showResult($box, text, isError) {
        $box
            .removeClass("is-error is-success")
            .addClass(isError ? "is-error" : "is-success")
            .text(text || "");
    }

    function sanitizeDir(input) {
        return String(input || "")
            .toLowerCase()
            .replace(/[^a-z0-9\-_]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }

    function positiveInt(value, fallback) {
        var parsed = parseInt(value, 10);
        return isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, "") + " MB";
        }
        return Math.ceil(bytes / 1024) + " KB";
    }

    function buildBatches(files) {
        var maxFiles = positiveInt(cfg.batchFileLimit, 10);
        var maxBytes = positiveInt(cfg.batchByteLimit, 8 * 1024 * 1024);
        var batches = [];
        var current = [];
        var currentBytes = 0;

        files.forEach(function (file) {
            var fileBytes = Number(file.size) || 0;
            var exceedsBatch = current.length && currentBytes + fileBytes > maxBytes;
            if (current.length >= maxFiles || exceedsBatch) {
                batches.push(current);
                current = [];
                currentBytes = 0;
            }

            current.push(file);
            currentBytes += fileBytes;
        });

        if (current.length) {
            batches.push(current);
        }

        return batches;
    }

    function getResponseMessage(data, fallback) {
        return (data && (data.msg || data.message)) || fallback;
    }

    function getAjaxError(xhr, textStatus, errorThrown) {
        var json = xhr && xhr.responseJSON;
        if (json && json.data) {
            return getResponseMessage(json.data, "上传失败");
        }

        var status = xhr ? Number(xhr.status) || 0 : 0;
        var responseText = xhr ? $.trim(String(xhr.responseText || "")) : "";
        if (status === 413) {
            return "本批文件超过服务器请求大小限制，请减少单个文件大小后重试。";
        }
        if (responseText === "-1" || status === 403) {
            return "请求校验失败，请刷新页面后重试。";
        }
        if (responseText === "0") {
            return "WordPress 未识别上传请求，请确认插件已启用并刷新页面重试。";
        }
        if (responseText && responseText.charAt(0) !== "<") {
            return responseText.slice(0, 240);
        }
        if (responseText) {
            return "服务器返回了非 JSON 内容，请检查 PHP 错误日志。";
        }
        if (status) {
            return "服务器请求失败（HTTP " + status + "）。";
        }
        return errorThrown || textStatus || "无法连接服务器。";
    }

    function uploadBatch(files, groupDir, groupTitle) {
        var deferred = $.Deferred();
        var fd = new FormData();
        fd.append("action", cfg.action);
        fd.append("nonce", cfg.nonce);
        fd.append("group_dir", groupDir);
        fd.append("group_title", groupTitle);
        fd.append("expected_count", files.length);
        files.forEach(function (file) {
            fd.append("emoji_files[]", file);
        });

        $.ajax({
            url: cfg.ajaxUrl,
            method: "POST",
            data: fd,
            processData: false,
            contentType: false,
            dataType: "json"
        })
            .done(function (res) {
                if (!res || !res.success) {
                    deferred.reject({
                        message: getResponseMessage(res && res.data, "上传失败")
                    });
                    return;
                }
                deferred.resolve(res.data || {});
            })
            .fail(function (xhr, textStatus, errorThrown) {
                deferred.reject({
                    message: getAjaxError(xhr, textStatus, errorThrown)
                });
            });

        return deferred.promise();
    }

    $(document).on("click", ".zibll-emoji-admin-upload-btn", function (e) {
        e.preventDefault();

        var $wrap = $(this).closest(".zibll-emoji-admin-uploader");
        var $result = $wrap.find(".zibll-emoji-admin-result");
        var $dir = $wrap.find('[name="zibll_emoji_group_dir"]');
        var $title = $wrap.find('[name="zibll_emoji_group_title"]');
        var $files = $wrap.find('[name="zibll_emoji_files"]');
        var fileInput = $files[0];

        var groupDir = sanitizeDir($dir.val());
        var groupTitle = $.trim($title.val());

        if (!groupDir) {
            showResult($result, "请填写分组目录（英文）。", true);
            $dir.focus();
            return;
        }
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            showResult($result, "请选择至少一张图片。", true);
            return;
        }

        var files = [];
        for (var i = 0; i < fileInput.files.length; i++) {
            files.push(fileInput.files[i]);
        }

        var maxFileBytes = positiveInt(cfg.maxFileBytes, 0);
        if (maxFileBytes) {
            for (var fileIndex = 0; fileIndex < files.length; fileIndex++) {
                if (files[fileIndex].size > maxFileBytes) {
                    showResult(
                        $result,
                        "文件“" + files[fileIndex].name + "”超过单文件上限 " + formatBytes(maxFileBytes) + "。",
                        true
                    );
                    return;
                }
            }
        }

        var batches = buildBatches(files);
        var $btn = $(this);
        var uploaded = 0;
        var failed = 0;

        $btn.prop("disabled", true);

        function runBatch(batchIndex) {
            if (batchIndex >= batches.length) {
                var summary = "上传完成：成功 " + uploaded + " 个";
                if (failed > 0) {
                    summary += "，失败 " + failed + " 个";
                }
                showResult($result, summary + "，页面即将刷新。", failed > 0);
                fileInput.value = "";
                setTimeout(function () {
                    window.location.reload();
                }, 700);
                $btn.prop("disabled", false);
                return;
            }

            showResult(
                $result,
                "正在上传第 " + (batchIndex + 1) + "/" + batches.length + " 批，已成功 " + uploaded + "/" + files.length + " 个...",
                false
            );

            uploadBatch(batches[batchIndex], groupDir, groupTitle)
                .done(function (data) {
                    uploaded += positiveInt(data.uploaded, batches[batchIndex].length);
                    failed += Math.max(0, parseInt(data.failed, 10) || 0);
                    runBatch(batchIndex + 1);
                })
                .fail(function (error) {
                    var prefix = uploaded > 0 ? "已成功上传 " + uploaded + " 个；" : "";
                    showResult(
                        $result,
                        prefix + "第 " + (batchIndex + 1) + " 批失败：" + (error.message || "上传失败"),
                        true
                    );
                    $btn.prop("disabled", false);
                });
        }

        runBatch(0);
    });

    function initSortManager($manager) {
        var sourceGroups = $.isArray(cfg.sortGroups) ? cfg.sortGroups : [];
        if (!cfg.sortAction || !cfg.sortNonce || !sourceGroups.length || !$.fn.sortable) {
            return;
        }

        var groups = [];
        var seenGroups = {};

        sourceGroups.forEach(function (sourceGroup) {
            var dir = String((sourceGroup && sourceGroup.dir) || "");
            if (!dir || seenGroups[dir]) {
                return;
            }

            var items = [];
            var seenFiles = {};
            ($.isArray(sourceGroup.items) ? sourceGroup.items : []).forEach(function (sourceItem) {
                var fileName = String((sourceItem && sourceItem.file) || "");
                if (!fileName || seenFiles[fileName]) {
                    return;
                }

                seenFiles[fileName] = true;
                items.push({
                    file: fileName,
                    name: String(sourceItem.name || fileName),
                    url: String(sourceItem.url || "")
                });
            });

            if (!items.length) {
                return;
            }

            seenGroups[dir] = true;
            groups.push({
                dir: dir,
                title: String(sourceGroup.title || dir),
                items: items
            });
        });

        if (!groups.length) {
            return;
        }

        var $groupList = $manager.find(".zibll-emoji-sort-groups");
        var $itemGrid = $manager.find(".zibll-emoji-sort-items");
        var $title = $manager.find(".zibll-emoji-sort-current-title");
        var $count = $manager.find(".zibll-emoji-sort-count");
        var $status = $manager.find(".zibll-emoji-sort-status");
        var $spinner = $manager.find(".spinner");
        var $save = $manager.find(".zibll-emoji-sort-save");
        var $reset = $manager.find(".zibll-emoji-sort-reset");
        var currentDir = groups[0].dir;
        var dirty = false;

        function findGroup(dir) {
            for (var i = 0; i < groups.length; i++) {
                if (groups[i].dir === dir) {
                    return groups[i];
                }
            }
            return null;
        }

        function setStatus(text, isError) {
            $status.toggleClass("is-error", !!isError).text(text || "");
        }

        function markDirty() {
            dirty = true;
            $save.prop("disabled", false);
            setStatus("有未保存的调整", false);
        }

        function syncGroupOrder() {
            var groupMap = {};
            groups.forEach(function (group) {
                groupMap[group.dir] = group;
            });

            var ordered = [];
            $groupList.children(".zibll-emoji-sort-group").each(function () {
                var dir = String($(this).attr("data-dir") || "");
                if (groupMap[dir]) {
                    ordered.push(groupMap[dir]);
                    delete groupMap[dir];
                }
            });

            groups.forEach(function (group) {
                if (groupMap[group.dir]) {
                    ordered.push(group);
                }
            });
            groups = ordered;
        }

        function syncCurrentItems() {
            var group = findGroup(currentDir);
            if (!group) {
                return;
            }

            var itemMap = {};
            group.items.forEach(function (item) {
                itemMap[item.file] = item;
            });

            var ordered = [];
            $itemGrid.children(".zibll-emoji-sort-item").each(function () {
                var fileName = String($(this).attr("data-file") || "");
                if (itemMap[fileName]) {
                    ordered.push(itemMap[fileName]);
                    delete itemMap[fileName];
                }
            });

            group.items.forEach(function (item) {
                if (itemMap[item.file]) {
                    ordered.push(item);
                }
            });
            group.items = ordered;
        }

        function renderGroups() {
            $groupList.empty();
            groups.forEach(function (group) {
                var $item = $('<li class="zibll-emoji-sort-group"></li>');
                var $button = $('<button type="button" class="zibll-emoji-sort-group-button"></button>');
                var $handle = $('<span class="dashicons dashicons-menu zibll-emoji-sort-group-handle" title="拖动分组"></span>');
                var $label = $('<span class="zibll-emoji-sort-group-label"></span>').text(group.title);
                var $number = $('<span class="zibll-emoji-sort-group-count"></span>').text(group.items.length);

                $item.attr("data-dir", group.dir).toggleClass("is-active", group.dir === currentDir);
                $button.attr("aria-label", "选择分组：" + group.title);
                $button.append($handle, $label, $number);
                $item.append($button);
                $groupList.append($item);
            });
            $groupList.sortable("refresh");
        }

        function renderItems() {
            var group = findGroup(currentDir);
            $itemGrid.empty();
            if (!group) {
                $title.text("");
                $count.text("");
                return;
            }

            $title.text(group.title);
            $count.text(group.items.length + " 张");

            group.items.forEach(function (item) {
                var $item = $('<div class="zibll-emoji-sort-item"></div>');
                var $handle = $('<span class="dashicons dashicons-move zibll-emoji-sort-item-handle" title="拖动表情"></span>');
                var $image = $("<img>");
                var $name = $('<span class="zibll-emoji-sort-item-name"></span>').text(item.name);

                $item.attr("data-file", item.file).attr("title", item.name);
                $image.attr({
                    src: item.url,
                    alt: item.name,
                    loading: "lazy",
                    decoding: "async"
                });
                $item.append($handle, $image, $name);
                $itemGrid.append($item);
            });
            $itemGrid.sortable("refresh");
        }

        function buildOrder() {
            syncGroupOrder();
            syncCurrentItems();

            var order = {
                groups: [],
                items: {}
            };
            groups.forEach(function (group) {
                order.groups.push(group.dir);
                order.items[group.dir] = group.items.map(function (item) {
                    return item.file;
                });
            });
            return order;
        }

        function setBusy(isBusy) {
            $spinner.toggleClass("is-active", !!isBusy);
            $reset.prop("disabled", !!isBusy);
            $save.prop("disabled", !!isBusy || !dirty);
        }

        $groupList.sortable({
            handle: ".zibll-emoji-sort-group-handle",
            items: "> .zibll-emoji-sort-group",
            tolerance: "pointer",
            placeholder: "zibll-emoji-sort-group-placeholder",
            update: function () {
                syncGroupOrder();
                markDirty();
            }
        });

        $itemGrid.sortable({
            handle: ".zibll-emoji-sort-item-handle",
            items: "> .zibll-emoji-sort-item",
            tolerance: "pointer",
            placeholder: "zibll-emoji-sort-item-placeholder",
            update: function () {
                syncCurrentItems();
                markDirty();
            }
        });

        $groupList.on("click", ".zibll-emoji-sort-group-button", function () {
            var nextDir = String($(this).closest(".zibll-emoji-sort-group").attr("data-dir") || "");
            if (!nextDir || nextDir === currentDir || !findGroup(nextDir)) {
                return;
            }

            syncCurrentItems();
            currentDir = nextDir;
            $groupList.children().removeClass("is-active");
            $(this).closest(".zibll-emoji-sort-group").addClass("is-active");
            renderItems();
        });

        $save.on("click", function () {
            if (!dirty) {
                return;
            }

            setBusy(true);
            setStatus("正在保存...", false);
            $.ajax({
                url: cfg.ajaxUrl,
                method: "POST",
                dataType: "json",
                data: {
                    action: cfg.sortAction,
                    nonce: cfg.sortNonce,
                    order: JSON.stringify(buildOrder())
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        setStatus(getResponseMessage(res && res.data, "保存失败"), true);
                        return;
                    }

                    dirty = false;
                    setStatus(getResponseMessage(res.data, "排序已保存"), false);
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    setStatus(getAjaxError(xhr, textStatus, errorThrown), true);
                })
                .always(function () {
                    setBusy(false);
                });
        });

        $reset.on("click", function () {
            if (!window.confirm("确定恢复为分组目录名和图片文件名的默认排序吗？")) {
                return;
            }

            setBusy(true);
            setStatus("正在恢复...", false);
            $.ajax({
                url: cfg.ajaxUrl,
                method: "POST",
                dataType: "json",
                data: {
                    action: cfg.sortAction,
                    nonce: cfg.sortNonce,
                    reset: 1
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        setStatus(getResponseMessage(res && res.data, "恢复失败"), true);
                        return;
                    }

                    dirty = false;
                    setStatus(getResponseMessage(res.data, "已恢复默认排序"), false);
                    window.location.reload();
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    setStatus(getAjaxError(xhr, textStatus, errorThrown), true);
                })
                .always(function () {
                    setBusy(false);
                });
        });

        window.addEventListener("beforeunload", function (event) {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = "";
        });

        renderGroups();
        renderItems();
        setStatus("当前排序已保存", false);
    }

    $(function () {
        $(".zibll-emoji-sort-manager").each(function () {
            initSortManager($(this));
        });
    });
})(jQuery);
