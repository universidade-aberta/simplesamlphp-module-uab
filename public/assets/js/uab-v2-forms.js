

"use strict";

/*
 * Floating labels implementation
 */
document.addEventListener('DOMContentLoaded', ()=>{
    const enabledFloatingEl = (el)=>{
        if(el.matches('input.form-control,select.form-control') && !!el.id && !!el.getAttribute('title') && !document.querySelector(`label[for="${el.id}"]`)){
            const label = document.createElement('label');
            label.setAttribute('for', el.id);
            label.setAttribute('tabindex', '-1');
            label.setAttribute('role', 'presentation');
            label.classList.add('form-control-label', 'foc-item', 'foc-item-required');
            label.innerHTML = el.getAttribute('title');

            const updateView = ()=>{
                const hasContent = el.value.length>0;
                el.parentElement.classList.toggle('as-label', hasContent);
                el.parentElement.classList.toggle('as-placeholder', !hasContent);
            };

            el.addEventListener('focus', ()=>{
                el.parentElement.classList.add('as-label');
                el.parentElement.classList.remove('as-placeholder');
            });
            el.addEventListener('blur', updateView);
            el.addEventListener('change', updateView);
            el.dispatchEvent(new FocusEvent('blur', {'relatedTarget': el}));
            el.parentElement.insertBefore(label, el);
            
            const line = document.createElement('span');
            line.classList.add('form-control-line');
            el.after(line);

            el.removeAttribute('title');
        }
    };
    const enabledFloatingEls = (rootEl)=>{
        enabledFloatingEl(rootEl);
        rootEl.querySelectorAll('input.form-control, select.form-control').forEach(el=>{
            enabledFloatingEl(el);
        });
    };
    enabledFloatingEls(document.body);

    const enabledTextTruncation = (rootEl)=>{
        const enableTextTruncationForEl = (el)=>{
            if(el.classList.contains('text-truncate')){
                el.addEventListener('mouseenter', (ev)=>{
                    if(ev.currentTarget.offsetWidth < ev.currentTarget.scrollWidth && !ev.currentTarget.getAttribute('title')) {
                        ev.currentTarget.setAttribute('title', ev.currentTarget.textContent);
                    }
                }, false);
            }
        };
        enableTextTruncationForEl(rootEl);
        rootEl.querySelectorAll('.text-truncate').forEach(el=>{
            enableTextTruncationForEl(el);
        });
    };
    enabledTextTruncation(document.body);

    const observer = new MutationObserver((mutationsList)=>{
        mutationsList.forEach((mutation)=>{
            mutation.addedNodes.forEach((el)=>{
                if(el?.nodeType === Node.ELEMENT_NODE){
                    enabledFloatingEls(el);
                    enabledTextTruncation(el);
                }
            });
        });
    });
    observer.observe(document.body, { subtree: true, childList: true });
});

/**
 * Fields restritions
 */
document.addEventListener('DOMContentLoaded', ()=>{


    const debounceFn = (func, timeout = 300)=>{
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                func.apply(this, args);
            }, timeout);
        };
    };

    const throttleFn = (func, ms = 50, context = window)=>{
        let to;
        let wait = false;
        return (...args) => {
            let later = () => {
                func.apply(context, args);
            };
            if (!wait) {
                later();
                wait = true;
                to = setTimeout(() => {
                    wait = false;
                }, typeof ms === 'function' ? ms.apply(context, args) : ms);
            }
        };
    };

    const applyFormRestritionsInElementFn = (form, el)=>{
        const restritions = el.dataset?.['restrictions'];
        const regex = new RegExp('{(?<operator>.+?)(:true\\((?<true>.+?)\\))?(:false\\((?<false>.+?)\\))?}(?<selector>.+?){\\/\\1}', 'gm');
        let m;
        while ((m = regex.exec(restritions)) !== null) {
            if (m.index === regex.lastIndex) {
                regex.lastIndex++;
            }
            const match = (form.querySelectorAll(m.groups.selector).length > 0);

            const groups = {
                'true': '-match',
                'false': '-do-not-match'
            };

            Object.keys(groups).forEach((group) => {
                const sufix = groups[group];
                if (!!m.groups?.[group]) {
                    const positive = group==='true';
                    const add = (match && positive) || (!match && !positive);
                    try {
                        const attributes = JSON.parse(m.groups[group]);
                        const parseAttributes = (el, attributes)=>{
                            Object.keys(attributes).forEach((attribute) => {
                                switch (attribute.toLowerCase()) {
                                    case '- data-restrictions -':
                                        const selector = attributes[attribute]?.selector;
                                        const innerAttributes = attributes[attribute]?.attributes;
                                        if(!!selector && !!innerAttributes){
                                            document.querySelectorAll(selector).forEach((subEl)=>{
                                                el.setAttribute('data-restrictions', el.getAttribute('data-restrictions').replace(m[0], '').toString());
                                                if(el.getAttribute('data-restrictions')?.length<=0){
                                                    el.removeAttribute('data-restrictions');
                                                }
                                                subEl.setAttribute('data-restrictions', (subEl.hasAttribute('data-restrictions')?subEl.getAttribute('data-restrictions'):'')+`{cloned-rule:${(positive?'true':'false')}(${(JSON.stringify(innerAttributes))})}${m.groups.selector}{/cloned-rule}`);
                                                applyFormRestritionsInElementFn(form, subEl);
                                            });
                                        }
                                        break;
                                    case 'class':
                                        const classList = attributes[attribute].split();
                                        el.classList[add ? 'add' : 'remove'](...classList);
                                        break;
                                    case 'style':
                                        try {
                                            const styles = JSON.parse(attributes[attribute]);
                                            Object.keys(styles).forEach((styleProperty) => {
                                                if (!!styles[styleProperty] && add) {
                                                    el.style.setProperty(styleProperty, styles[styleProperty]);
                                                } else {
                                                    el.style.removeProperty(styleProperty);
                                                }
                                            }
                                            );
                                        } catch (ex) {
                                            console.error(m.groups[group], attributes[attribute], ex);
                                        }
                                        ; break;
                                    default:
                                        if (match && add) {
                                            el.setAttribute(attribute, attributes[attribute]);
                                        } else {
                                            el.removeAttribute(attribute);
                                        }
                                }
                            });
                        };
                        parseAttributes(el, attributes);
                    } catch (ex) {
                        console.error(m.groups[group], ex);
                    }
                } else {
                    el.classList.toggle(m.groups.operator + sufix, match);
                }
            });
        }
    };

    const applyFormRestritionsFn = (form)=>{
        form.querySelectorAll(`[data-restrictions]`).forEach(el => {
            applyFormRestritionsInElementFn(form, el);
        });
    };

    const checkElementValidityFn = (el)=>{
        if ('checkValidity' in el) {
            const previousValidity = el.getAttribute('data-is-valid');
            const currentValidity = el.checkValidity() ? 'true' : 'false';

            el.setAttribute('data-is-valid', currentValidity);
            if (previousValidity !== currentValidity) {
                const form = el.closest('form');
                if (!!form) {
                    applyFormRestritionsFn(form);
                }
            }
        }
    };

    const checkValidityFn = (form)=>{
        form.querySelectorAll(`[name]`).forEach(el => {
            checkElementValidityFn(el);
        });
        applyFormRestritionsFn(form);
    }

    const syncValuesFn = (input)=>{
        if(input.hasAttribute('value') && input.getAttribute('value')!=input.value){
            input.setAttribute('value', input.value);
        }
    }

    const checkElementValidityAsync = debounceFn((el) => {
        checkElementValidityFn(el);
    }, 300);

    const checkValidityAsync = debounceFn((form) => {
        checkValidityFn(form);
    }, 300);

    const syncValuesFnAsync = debounceFn((input) => {
        syncValuesFn(input);
    }, 150);

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('change', () => {
            checkValidityAsync(form);
        });
        const inputs = form.elements;
        for (let i = 0; i < inputs.length; i++) {
            ['keyup', 'change'].forEach((ev) => inputs[i].addEventListener(ev, (ev) => {
                syncValuesFnAsync(ev.currentTarget);
                // checkElementValidityAsync(ev.currentTarget);
                checkValidityAsync(form);
            }));
        }
        checkValidityAsync(form);
    });
});