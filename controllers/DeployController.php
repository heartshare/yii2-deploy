<?php
/**
 * Author: Eugine Terentev <eugine@terentev.net>
 */
namespace trntv\deploy\controllers;

use trntv\deploy\base\Server;
use trntv\deploy\base\Task;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\console\Controller;
use yii\di\Container;
use yii\di\Instance;
use yii\di\ServiceLocator;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\web\ErrorAction;

class DeployController extends Controller{

    /**
     * @var \yii\di\ServiceLocator
     */
    public $container;
    private $_tasks;

    public function init(){
        $this->container = new ServiceLocator();
    }

    public function actionIndex($recipe, $servers){
        // Load recipe
        $recipe = \Yii::getAlias($recipe);
        if(!file_exists($recipe)){
            throw new InvalidParamException('Wrong recipe file path');
        }

        $recipe = require($recipe);
        if(!is_array($recipe) || !isset($recipe['tasks'])){
            throw new InvalidConfigException('Recipe must include tasks');
        }

        // Set servers
        $serversConfig = require(\Yii::getAlias($servers));
        $this->registerServers($serversConfig);

        // Set services
        $this->registerServices(ArrayHelper::getValue($recipe, 'services'));

        // Set tasks
        $this->registerTasks(ArrayHelper::getValue($recipe, 'tasks'));

        if($this->runTasks() === false){
            Console::error('Error!');
            return Controller::EXIT_CODE_ERROR;
        };
        Console::output('Success!');
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Register servers
     * @param $serversConfig
     */
    protected function registerServers(array $serversConfig){
        foreach($serversConfig as $id => $component){
            $this->container->set("servers.$id", $component);
        }
    }

    public function registerServices(array $servicesConfig){
        foreach($servicesConfig as $id => $component){
            $this->container->set("services.$id", $component);
        }
    }

    public function registerTasks($tasksConfig){
        foreach($tasksConfig as $id => $task){
            $this->container->set("tasks.$id", [
                'class'=>Task::className(),
                'id'=>$id,
                'closure'=>$task
            ]);
            $this->_tasks[] = $id;
        }
    }

    protected function runTasks(){
        foreach($this->_tasks as $k => $id){
            Console::output(sprintf('Running task "%s" (%d/%d)', $id, $k+1, count($this->_tasks)));
            $result = $this->container
                ->get("tasks.$id")
                ->run(
                    $this->container,
                    $this
                );
            if($result === false){
                Console::error(sprintf('Task "%s" failed.', $id));
                return false;
            }
        }
        return true;
    }
}