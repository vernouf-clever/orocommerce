import GrapesJS from 'grapesjs';
import {uniqueId, each} from 'underscore';
import $ from 'jquery';

const componentHtmlIdRegexp = /(<div id="isolation-scope-([\w]*))/g;
// @deprecated
const componentCssIdRegexp = /(\[id="isolation-scope-([\w]*)"\])/g;
const cssSelectorRegexp = /(?:[\.\#])[\#\.\w\:\-\s\(\)\[\]\=\"]+\s?(?=\{)/g;
const cssWrapperScopeRegexp = /^#isolation-scope-[\w]+\{/;
const cssChildrenScopeRegexp = /#isolation-scope-[\w]*\s+/g;

const FORBIDDEN_ATTR = ['draggable', 'data-gjs[-\\w]+'];

const IMAGE_EXP_REGEXP = /(src|srcset)="(\{\{([\w\s\'\_\-\,\(\)]+)\}\})"/g;
const DATA_IMAGE_EXP_REGEXP = /(data-src-exp|data-srcset-exp)="(\{\{([\w\s\'\_\-\,\(\)]+)\}\})"/g;

/**
 * Test regexp
 * Prevent shift regexp index
 * @param html
 * @returns {boolean}
 */
const hasIsolation = html => {
    componentHtmlIdRegexp.lastIndex = 0;
    return componentHtmlIdRegexp.test(html);
};

export const escapeImageExpression = html => {
    return html.replace(IMAGE_EXP_REGEXP, (input, src) => {
        return input.replace(src, `data-${src}-exp`);
    });
};

export const normalizeImageExpression = html => {
    return html.replace(DATA_IMAGE_EXP_REGEXP, (input, exp) => {
        return input.replace(exp, exp.replace(/data-|-exp/g, ''));
    });
};

export const removeImageExpression = html => {
    return html.replace(IMAGE_EXP_REGEXP, '');
};

export const escapeWrapper = html => {
    html = escapeImageExpression(html);
    if (hasIsolation(html)) {
        html = $(html).html();
        html = escapeWrapper(html);
    }

    return html;
};

export const getWrapperAttrs = html => {
    const attrs = {};
    if (hasIsolation(html)) {
        const $wrapper = $(escapeImageExpression(html));
        each($wrapper[0].attributes, attr => attrs[attr.name] = $wrapper.attr(attr.name));
    }
    delete attrs.id;
    return attrs;
};

export const stripRestrictedAttrs = html => {
    FORBIDDEN_ATTR.forEach(attr => {
        html = html.replace(new RegExp(`([\\s])${attr}((=\"([^"]*)\")|(=\'([^']*)\'))?`, 'g'), '');
    });

    return html;
};

function randomId(length = 20) {
    return uniqueId(
        [...Array(length)].map(i => (~~(Math.random() * 36)).toString(36)).join('')
    );
}

export default GrapesJS.plugins.add('grapesjs-style-isolation', (editor, options) => {
    const scopeId = 'isolation-scope-' + randomId();

    editor.getIsolatedHtml = content => {
        const wrapper = editor.getWrapper();
        const wrapperClasses = wrapper.getClasses().join(' ');
        let html = stripRestrictedAttrs(escapeWrapper(editor.getHtml()), editor.getAllowedConfig());

        if (content) {
            html = content;
        }

        if (wrapperClasses.length || wrapper.styleToString().length || html.length) {
            const root = document.createElement('div');

            root.id = scopeId;
            root.innerHTML = html;

            if (wrapperClasses.length) {
                root.className = wrapperClasses;
            }

            html = root.outerHTML;
        }

        return normalizeImageExpression(html);
    };

    editor.getIsolatedCss = () => {
        const wrapperCss = editor.getWrapper().styleToString();
        let css = '';

        if (wrapperCss.length) {
            css += `#${scopeId}{${wrapperCss}}`;
        }

        css += editor.CssComposer.getAll().reduce((acc, rule) => {
            // Do not remove space in replace phrase
            acc += rule.toCSS().replace(cssSelectorRegexp, ` #${scopeId} $&`);
            return acc;
        }, '');

        return css;
    };

    editor.setIsolatedHtml = html => escapeWrapper(html);

    editor.getPureStyle = (css = '') => {
        if (!css.length) {
            return '';
        }

        css = css
            .replace(cssWrapperScopeRegexp, '#wrapper{')
            .replace(componentCssIdRegexp, '')
            .replace(cssChildrenScopeRegexp, '');

        const _res = editor.Parser.parseCss(css).reduce((acc, rule, index, collection) => {
            const {state = '', atRuleType = '', mediaText = '', selectorsAdd = ''} = rule;
            const key = rule.selectors.join('') + state + atRuleType + mediaText + selectorsAdd;

            acc[key] = $.extend(true, acc[key] || {}, rule);
            return acc;
        }, {});

        return Object.values(_res);
    };
});
