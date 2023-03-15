

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

    const uid = (element, prefix='')=>{
        if(!element.id){
            const genUid = () =>prefix+String(Date.now().toString(32)+Math.random().toString(16)).replace(/\./g, '');
            let id = genUid();
            while(!!document.getElementById(id)){
                id = genUid();
            }
            element.id = id;
        }
		return element.id;
	};

    const applyFormRestritionsInElementFactory = ()=>{
        // const restrictionsCache = new Map();
        const restrictionsCache = new Map();

        const parseRule = (rule, name, el, form, set=false, rootMatch=null)=>{
            
            const matchSelector = (!!rootMatch?rootMatch:(rule?.match));
            const match = !!matchSelector ? (form.querySelectorAll(matchSelector).length > 0) : set;

            Object.keys(rule).filter(key=>key.toLowerCase()!=='match').forEach(key=>{
                switch(key.toLowerCase()){
                    case 'if':
                        const T = (new Boolean(match)).toString();
                        const F = (new Boolean(!match)).toString()
                        const matchRules = rule[key][T]; 
                        const mismatchRules = rule[key][F]; 
                        if(!!mismatchRules){
                            rule[key][F] = parseRule(mismatchRules, `${name}-${key}`, el, form, match?!match:match);
                        }
                        if(!!matchRules){
                            rule[key][T] = parseRule(matchRules, `${name}-${key}`, el, form, match?match:!match);
                        }
                        break;
                    
                    case 'nested':
                        const nestedRules = !Array.isArray(rule[key])?[rule[key]]:rule[key];
                        nestedRules.forEach((subRule, index)=>{
                            if(!!subRule?.select){
                                document.querySelectorAll(subRule?.select).forEach((subEl)=>{
                                    el.setAttribute('data-restrictions', el.getAttribute('data-restrictions').replace(JSON.stringify({[key]:subRule}), '').toString());
                                    if(el.getAttribute('data-restrictions')?.length<=0){
                                        el.removeAttribute('data-restrictions');
                                    }
                                    
                                    let subRestritions;
                                    try{
                                        subRestritions = subEl.hasAttribute('data-restrictions')?JSON.parse(subEl.getAttribute('data-restrictions')):{};
                                    }catch(ex){
                                        subRestritions = {};
                                    }
                                    delete subRule?.select;
                                    subRestritions[`${name}-${key}-${index}`] = {"match": (rule?.match), ...subRule};
                                    subEl.setAttribute('data-restrictions', JSON.stringify(subRestritions));
                                    init(form, subEl);
                                });
                            }
                        });

                        delete rule[key];
                        break;
                    
                    case 'class':
                        const classList = rule[key].split(' ');
                        el.classList[match ? 'add' : 'remove'](...classList);
                        break;

                    default: 
                        if (match) {
                            el.setAttribute(key, rule[key]);
                        } else {
                            el.removeAttribute(key);
                        }
                }
            });
            return rule;
        };

        const parseRules = (rules, el, form)=>{
            Object.keys(rules).forEach((rule) => {
                rules[rule] = parseRule(rules[rule], rule, el, form);
            });
            return rules;
        };

        const init = (form, el)=>{
            const restritions = el.dataset?.['restrictions'];

            try{
                const elID = uid(el); 
                if(!restrictionsCache.has(elID)){
                    restrictionsCache.set(elID, JSON.parse(restritions));
                }
                parseRules(restrictionsCache.get(elID), el, form);
            }catch(ex){
                console.error("Data Restritions Exception", ex, restritions);
            }
        };

        return init;
    };
    const applyFormRestritionsInElementFn = applyFormRestritionsInElementFactory();

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

    /**
     * Animate the toggling of hidden elements
     */
    const resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            entry.target.style.setProperty('--height', (entry.target.scrollHeight+20)+'px');
        }
    });
    document.addEventListener('DOMContentLoaded', ()=>{
        document.querySelectorAll(`.expandable-element`).forEach((el)=>{
            resizeObserver.observe(el);
            el.style.setProperty('--height', (el.scrollHeight+20)+'px');
        });
    });
});