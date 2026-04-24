document.addEventListener("DOMContentLoaded", function () {
    const box = document.querySelector(".professor-box");

    if (box) {
        box.style.opacity = "0";
        box.style.transform = "translateY(20px)";
        box.style.transition = "all 0.5s ease";

        setTimeout(() => {
            box.style.opacity = "1";
            box.style.transform = "translateY(0)";
        }, 100);
    }

});