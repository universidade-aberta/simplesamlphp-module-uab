document.documentElement.classList.toggle('no-js', false);
document.documentElement.classList.toggle('js', true);

document.addEventListener("DOMContentLoaded", ()=>{
    const randomDelay = Math.round(Math.random()*100)/100;
    document.querySelectorAll('body, .background').forEach(el=>{
        const currentAnimationDuration = /*getComputedStyle(el).animationDuration??*/'60s';
        el.style.setProperty("--color-animation-delay", `calc(${currentAnimationDuration} * -${randomDelay})`);
    });

    document.querySelectorAll('.square').forEach(el=>{
        const currentAnimationDuration = getComputedStyle(el).animationDuration??'60s';
        const randomDelay = Math.round(Math.random()*100)/100;
        el.style.setProperty("--animation-delay", `calc(${currentAnimationDuration} * -${randomDelay})`);
        const randomRotation = Math.round(Math.random()*360);
        el.style.setProperty("--initial-rotation", `${randomRotation}deg`);
        const scaleFactor = Math.round(Math.random()*3)+1;
        el.style.setProperty("--scale-multiplier", `${scaleFactor}`);
    });
});

window.addEventListener('load',()=>{
    const loginForm = document.querySelector(`#f.uab-login-form`);
    if(!!loginForm){
        const debounce = (func, timeout = 300)=>{
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    func.apply(this, args);
                }, timeout);
            };
        };

        const domUniqueID = (prefix='')=>{
            let id;
            do{
                id= prefix+(Math.random().toString(36).substring(2, 11));
            }while(document.getElementById(id));
            return id;
        };

        const checkElementValidity = (el)=>{
            if ('checkValidity' in el) {

                const valueMissing = el.getAttribute("data-error-empty");
                if ((el.validity.valueMissing) && !!valueMissing) {
                    el.setCustomValidity(valueMissing);
                } else {
                    el.setCustomValidity("");
                }

                const validity = el.checkValidity();

                let errorEl;
                if(!el.hasAttribute('data-error-message') || !(errorEl = document.getElementById(el.getAttribute('data-error-message')))){
                    const id = (el.hasAttribute('id')?el.id:domUniqueID());
                    errorEl = document.createElement('div');
                    errorEl.setAttribute('id', 'error_message_'+id);
                    errorEl.classList.add('login-error-message');
                    errorEl.setAttribute('aria-labelledby', id);
                    el.insertAdjacentElement('afterend', errorEl);
                    el.setAttribute('data-error-message', errorEl.id);
                    if(el.id !== id){
                        el.setAttribute('id', id);
                    }
                }
                if(validity){
                    errorEl.removeAttribute('role');
                    errorEl.textContent = '';
                    errorEl.style.display = 'none';
                    el.removeAttribute('aria-errormessage');
                }else{
                    errorEl.setAttribute('role', 'alert');
                    errorEl.textContent = '';
                    errorEl.style.display = 'block';
                    errorEl.textContent = el.validationMessage;
                    el.setAttribute('aria-errormessage', errorEl.id);
                }

                el.setAttribute('aria-invalid', !validity);
                return validity;
            }
            return true;
        };

        const checkElementValidityAsync = debounce((el) => {
            checkElementValidity(el);
        }, 300);

        const updateSubmitButtonState = (submitButton, validity=false)=>{
            if(!!submitButton){
                if(validity){
                    submitButton.removeAttribute('disabled');
                }else{
                    submitButton.setAttribute('disabled', '');
                }
            }
            return validity;
        };

        const updateFormValidity = (form, submitButton)=>{
            const validity = form.checkValidity();
            form.setAttribute('aria-invalid', !validity);
            //updateSubmitButtonState(submitButton, validity);
            return validity;
        };

        const usernameField = loginForm.querySelector('#username');
        const passwordField = loginForm.querySelector('#password');
        const fields = [usernameField, passwordField];
        const submitButton = document.getElementById("submit_button");

        if(!!submitButton){
            submitButton.onclick = ()=>{};
        }

        fields.forEach(el => {
            ['input', 'blur'].forEach((ev) => el.addEventListener(ev, () => {
                checkElementValidityAsync(el);
            }));
        });

        loginForm.addEventListener('input', (ev) => {
            updateFormValidity(ev.currentTarget, submitButton);

            if(loginForm.hasAttribute('aria-errormessage')){
                loginForm.removeAttribute('aria-errormessage');
            }
        });
        loginForm.addEventListener('submit', (ev) => {
            updateFormValidity(ev.currentTarget, submitButton);
            ev.currentTarget.focus();
            let invalidFields = [];
            if ((invalidFields = fields.filter((field) => field && !checkElementValidity(field))) && invalidFields.length>0) {
                invalidFields[0].focus();
                //invalidFields[0].reportValidity();
                ev.preventDefault();
                return false;
            }

            if(!!submitButton){
                submitButton.innerHTML = submitButton.getAttribute("data-processing");
                submitButton.disabled = true;
            }
        });
        loginForm.setAttribute('tabindex', '-1');
        loginForm.setAttribute('novalidate', '');
        loginForm.focus();
        //updateSubmitButtonState(submitButton, loginForm.checkValidity());

    }
});