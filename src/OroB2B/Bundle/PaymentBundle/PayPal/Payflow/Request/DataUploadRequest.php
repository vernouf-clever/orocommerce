<?php

namespace OroB2B\Bundle\PaymentBundle\PayPal\Payflow\Request;

use OroB2B\Bundle\PaymentBundle\PayPal\Payflow\Option\Transaction;

class DataUploadRequest extends AbstractRequest
{
    /** {@inheritdoc} */
    public function getAction()
    {
        throw new \BadMethodCallException();

        return Transaction::DATA_UPLOAD;
    }
}
