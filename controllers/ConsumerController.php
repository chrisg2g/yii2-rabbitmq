<?php

namespace mikemadisonweb\rabbitmq\controllers;

use common\components\rabbitmq\BaseRabbitMQ;
use common\components\rabbitmq\Consumer;
use yii\console\Controller;
use yii\helpers\Console;

class ConsumerController extends Controller
{
    public $memoryLimit;
    public $route;
    public $amount = 0;
    public $debug = false;
    public $withoutSignals = false;

    protected $consumer;

    protected $options = [
        'm' => 'messages',
        'l' => 'memoryLimit',
        'r' => 'route',
        'd' => 'debug',
        'w' => 'withoutSignals',
    ];

    /**
     * @param string $actionID
     * @return array
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), array_values($this->options));
    }

    /**
     * @return array
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), $this->options);
    }

    /**
     * Force stop consumer
     */
    public function stopConsumer()
    {
        if ($this->consumer instanceof Consumer) {
            // Process current message, then halt consumer
            $this->consumer->forceStopConsumer();
            // Halt consumer if waiting for a new message from the queue
            try {
                $this->consumer->stopConsuming();
            } catch (\Exception $e) {
            }
        }
    }

    public function restartConsumer()
    {
        // TODO: Implement restarting of consumer
    }

    public function init()
    {
        \Yii::$app->rabbitmq->load();
    }

    /**
     * Run consumer(one instance per queue)
     * @param    string    $name    Consumer name
     * @return   int|null
     */
    public function actionSingle($name)
    {
        $this->setOptions();
        $serviceName = sprintf(BaseRabbitMQ::CONSUMER_SERVICE_NAME, $name);
        $this->consumer = $this->getConsumer($serviceName);

        return $this->consumer->consume($this->amount);
    }

    /**
     * Run consumer(one instance per multiple queues)
     * @param    string    $name    Consumer name
     * @return   int|null
     */
    public function actionMultiple($name)
    {
        $this->setOptions();
        $serviceName = sprintf(BaseRabbitMQ::MULTIPLE_CONSUMER_SERVICE_NAME, $name);
        $this->consumer = $this->getConsumer($serviceName);

        return $this->consumer->consume($this->amount);
    }

    /**
     * Set options passed by user
     */
    private function setOptions()
    {
        if (defined('AMQP_WITHOUT_SIGNALS') === false) {
            define('AMQP_WITHOUT_SIGNALS', $this->withoutSignals);
        }
        if (!AMQP_WITHOUT_SIGNALS && extension_loaded('pcntl')) {
            if (!function_exists('pcntl_signal')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal' is referenced in the php.ini 'disable_functions' and can't be called.");
            }
            pcntl_signal(SIGTERM, [&$this, 'stopConsumer']);
            pcntl_signal(SIGINT, [&$this, 'stopConsumer']);
            pcntl_signal(SIGHUP, [&$this, 'restartConsumer']);
        }
        $this->setDebug();

        if (!is_numeric($this->amount) || 0 > $this->amount) {
            throw new \InvalidArgumentException('The -m option should be null or greater than 0');
        }
    }

    /**
     * @param  string   $serviceName
     * @return Consumer
     */
    private function getConsumer($serviceName)
    {
        $consumer = \Yii::$container->get($serviceName);
        if ((null !== $this->memoryLimit) && ctype_digit((string)$this->memoryLimit) && ($this->memoryLimit > 0)) {
            $consumer->setMemoryLimit($this->memoryLimit);
        }
        if (null !== $this->route) {
            $consumer->setRoutingKey($this->route);
        }

        return $consumer;
    }

    private function setDebug()
    {
        if (defined('AMQP_DEBUG') === false) {
            if ($this->debug === 'false') {
                $this->debug = false;
            }
            define('AMQP_DEBUG', (bool)$this->debug);
        }
    }
}