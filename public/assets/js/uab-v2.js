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