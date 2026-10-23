<?php

namespace App\Exceptions;

/**
 * Los sellos o el premio del cliente cambiaron entre que se calculó la venta
 * y se guardó (otra tablet le cobró al mismo tiempo). La venta no se guarda.
 */
class LoyaltyChangedException extends \RuntimeException {}
