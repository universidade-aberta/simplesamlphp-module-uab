document.documentElement.classList.toggle('no-js', false);
document.documentElement.classList.toggle('js', true);

document.addEventListener("DOMContentLoaded", ()=>{
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    canvas.classList.add('bg-animation');
    
    const resizeCanvas = ()=>{
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    };
    
    class Square{
        ctx;
        x;
        y;
        speed;
        size;
        angle;
        
        constructor(ctx, x, y, speed, size, angle=0) {
            this.ctx = ctx;
            this.x = x;
            this.y = y;
            this.speed = speed;
            this.size = size;
            this.angle = angle;
        }
        
        draw() {
            this.ctx.save();
            this.ctx.translate(this.x, this.y);
            this.ctx.rotate(this.angle);
            this.ctx.strokeStyle = "rgba(255,255,255,0.2)";
            this.ctx.fillStyle = "rgba(255,255,255,0)";
            this.ctx.lineWidth = 3;
            this.ctx.beginPath();
            this.ctx.rect(-this.size / 2, -this.size / 2, this.size, this.size);
            this.ctx.stroke();
            this.ctx.fill();
            this.ctx.restore();
        }
    }
    
    // Initialize squares with random positions and sizes
    const squares = Array.from({ length: 3 }, () => {
        const x = Math.random() * (window.innerWidth + 200) - 100;
        const y = Math.random() * (window.innerHeight + 200) - 100;
        const speed = Math.random() * 0.003;
        const size = Math.random() * window.innerWidth/2 + window.innerWidth/2;
        const angle = (Math.random() * 360) * Math.PI / 180;
        return new Square(ctx, x, y, speed, size, angle);
    });

    

    // Check if user prefers reduced motion
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    let animationEnabled = !prefersReducedMotion?.matches;

    const draw = ()=>{
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    
        for (const square of squares) {
            square.draw();
            square.angle += square.speed;
        }

        if(animationEnabled) requestAnimationFrame(draw);
    };

    const randomDelay = Math.round(Math.random()*100)/100;
    const currentAnimationDuration = /*getComputedStyle(el).animationDuration??*/'60s';
    canvas.style.setProperty("--color-animation-delay", `calc(${currentAnimationDuration} * -${randomDelay})`);
    
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();

    // Function to handle the change in motion preference
    const handleReduceMotionChanged = () => {
        animationEnabled = !prefersReducedMotion?.matches;
        draw();
    };
    // Listen for changes to the motion preference
    prefersReducedMotion.addEventListener("change", handleReduceMotionChanged);

    // Initially check for the user's preference and start drawing
    handleReduceMotionChanged();

    document.body.appendChild(canvas);
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

        let errorMessageEl;
        if(loginForm.getAttribute('aria-invalid')=='true' && (loginForm.hasAttribute('aria-errormessage')) && (errorMessageEl=document.getElementById(loginForm.getAttribute('aria-errormessage')))){
            errorMessageEl.setAttribute('tabindex', '-1');
            errorMessageEl.focus();
        }else{
            loginForm.focus();
        }
        //updateSubmitButtonState(submitButton, loginForm.checkValidity());

    }
});