document.addEventListener("DOMContentLoaded", function () {

    // Selecciona la caja del profesor del HTML
    const box = document.querySelector(".professor-box");

    // Si existe la caja:
    if (box) {

        // Estado inicial: invisible y hacia abajo
        box.style.opacity = "0";
        box.style.transform = "translateY(20px)";

        // Transición
        box.style.transition = "all 0.5s ease";

        // Después de 100ms hace la animación
        setTimeout(() => {
            box.style.opacity = "1";
            box.style.transform = "translateY(0)";
        }, 100);
    }

});