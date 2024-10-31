
function create(htmlStr) {
    var frag = document.createDocumentFragment(),
        temp = document.createElement('div');
    temp.innerHTML = htmlStr;
    while (temp.firstChild) {
        frag.appendChild(temp.firstChild);
    }
    return frag;
}

var countDownDate = new Date(PHP_WARNING_VARIABLES.cycleCloseTime).getTime();

// Update the count down every 1 second
var x = setInterval(function() {

    // Get today's date and time
    var now = new Date().getTime();

    // Find the distance between now and the count down date
    var distance = countDownDate - now;

    if (distance < PHP_WARNING_VARIABLES.warningTime*60*1000) {

        // Time calculations for days, hours, minutes and seconds
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        var timeString = hours + "h " + minutes + "m " + seconds + "s ";


        if (!document.getElementById("warning-time")) {
            var newWarningParapgraph = create('<p id="warning-time" class="woocommerce-store-notice demo_store" style="background-color: #c62222; z-index:100;" data-position="bottom"><a href="#" class="woocommerce-store-notice__dismiss-link">Dispensar</a></p>');
            document.body.insertBefore(newWarningParapgraph, document.body.childNodes[0]);
        }
        document.getElementById("warning-time").innerHTML = 'Faltam ' + timeString + ' para o ciclo fechar!';

        if (distance < 0) {
            clearInterval(x);
            document.getElementById("warning-time").style.display = 'none';
        }
    }
}, 1000);