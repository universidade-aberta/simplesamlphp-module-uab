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
        const checkElementValidity = (el)=>{
            if ('checkValidity' in el) {
                el.reportValidity();
                el.setAttribute('aria-invalid', !el.validity.valid);
            }
        };

        const checkElementValidityAsync = debounce((el) => {
            checkElementValidity(el);
            updateFormValidity(el.closest('form'));
        }, 300);

        const usernameField = loginForm.querySelector('#username');
        const passwordField = loginForm.querySelector('#password');
        const fields = [usernameField, passwordField];
        const submitButton = document.getElementById("submit_button");

        const updateFormValidity = (form)=>{
            const validity = form.checkValidity();
            form.setAttribute('aria-invalid', !validity);
            if(form.hasAttribute('aria-errormessage')){
                form.removeAttribute('aria-errormessage');
            }
            if(!!submitButton){
                if(validity){
                    submitButton.removeAttribute('disabled');
                }else{
                    submitButton.setAttribute('disabled', '');
                }
            }
        };

        fields.forEach(el => {
            ['input'].forEach((ev) => el.addEventListener(ev, () => {
                checkElementValidityAsync(el);
            }));
        });

        loginForm.addEventListener('submit', (ev) => {
            updateFormValidity(ev.currentTarget);
            if (!(!fields.some((field) => !field || !field.reportValidity()))) {
                ev.preventDefault();
                return false;
            }

            if(!!submitButton){
                submitButton.onclick = ()=>{};
                submitButton.innerHTML = submitButton.getAttribute("data-processing");
                submitButton.disabled = true;
            }
        });

        if(!!submitButton){
            submitButton.onclick = ()=>{};
        }

        updateFormValidity(loginForm);
    }
});