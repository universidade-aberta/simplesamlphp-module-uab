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
    });
});