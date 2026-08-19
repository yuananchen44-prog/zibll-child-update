/**
 * 子比子主题 - 控制台品牌横幅过滤器（客户端）
 *
 * 父主题的部分前端 JS 会在浏览器控制台打印品牌调试信息，本脚本通过
 * 轻量地 monkey-patch console.log，在不影响其他日志的前提下静默这些输出，
 * 保持控制台整洁。
 *
 * 被屏蔽的信息（来源均已对照父主题源码核实）：
 *   1. 数据库查询统计
 *        console.log("数据库查询：X次 | 页面生成耗时：Xms")
 *        [来源: inc/functions/zib-footer.php :: zib_win_console()]
 *        —— 注意：该输出同时已由子主题服务端 remove_action 移除，
 *           此处规则作为双保险，命中即静默。
 *   2. 品牌横幅
 *        console.log("... %c Zibll Theme %c https://zibll.com ...")
 *        [来源: js/main.js:4829 / js/admin-main.js:463]
 *   3. 小工具提示
 *        console.log("Zibll Widget")
 *        [来源: js/widget-set.js:12]
 *
 * 加载说明（由子主题服务端注入）：
 *   - 通过 wp_enqueue_script 以 action 优先级 1、在 <head> 中（非 footer）加载，
 *     确保早于父主题 JS（位于 footer）完成对 console.log 的包装，
 *     从而拦截其后所有符合规则的 console.log 调用。
 *
 * 范围说明：仅拦截 console.log；console.error / console.warn /
 * console.info 等其他方法不受影响，正常错误与告警仍会显示。
 */
(function () {
    var _originalLog = console.log;

    console.log = function () {
        var i, arg;

        // 逐个检查参数中是否包含需要屏蔽的内容
        for (i = 0; i < arguments.length; i++) {
            arg = arguments[i];

            if (typeof arg === 'string') {
                // 屏蔽：数据库查询统计信息
                if (arg.indexOf('数据库查询') !== -1 && arg.indexOf('页面生成耗时') !== -1) {
                    return;
                }

                // 屏蔽：Zibll Theme 横幅（同时含 "Zibll Theme" 与 "zibll.com" 链接）
                if (arg.indexOf('Zibll Theme') !== -1 && arg.indexOf('zibll.com') !== -1) {
                    return;
                }

                // 屏蔽：Zibll Widget
                if (arg === 'Zibll Widget') {
                    return;
                }
            }
        }

        // 未命中过滤规则，正常输出
        return _originalLog.apply(console, arguments);
    };
})();
