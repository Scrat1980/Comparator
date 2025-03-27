<?php

namespace app\controllers;

use Yii;

/**
 * FlashTrait
 */
trait FlashTrait
{
    /**
     * @param string $message
     * @return $this
     */
    protected function success($message)
    {
        return $this->flash('success', $message);
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function info($message)
    {
        return $this->flash('info', $message);
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function warning($message)
    {
        return $this->flash('warning', $message);
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function error($message)
    {
        return $this->flash('error', $message);
    }

    /**
     * @param string $type
     * @param string $message
     * @return $this
     */
    protected function flash($type, $message)
    {
        Yii::$app->session->setFlash($type, $message);
        return $this;
    }
}
