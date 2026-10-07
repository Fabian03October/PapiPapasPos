<style>
    @page { size: 58mm auto; margin: 0; }
    * { box-sizing: border-box; }
    html, body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        -webkit-font-smoothing: none;
        font-smooth: never;
    }
    /* El rollo mide 58mm pero la cabeza térmica solo imprime ~48mm: con
       58mm de ancho se cortaba la orilla derecha (salía "$35.0"). */
    body {
        width: 48mm;
        margin: 0;
        padding: 2mm 1mm;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: bold;
        color: #000;
    }
    /* La cuchilla queda ~1.5cm arriba de la cabeza, así que lo último que se
       imprime todavía no ha salido cuando corta (se perdía el "Gracias por
       tu compra"). Este espacio empuja el final del ticket fuera de la
       cuchilla; el punto es para que el driver no recorte el espacio por
       venir en blanco. */
    body::after {
        content: ".";
        display: block;
        margin-top: 15mm;
        font-size: 8px;
        text-align: center;
    }
    p, tr, .line { break-inside: avoid; page-break-inside: avoid; }
    p { margin: 3px 0; }
    .center { text-align: center; }
    .right { text-align: right; }
    .line { border-top: 2px dashed #000; margin: 6px 0; }
</style>
