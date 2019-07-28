<?php

namespace Ray\Dyii;

class RayCWebApplication extends \CWebApplication
{
    /**
     * {@inheritdoc}
     *
     * @psalm-suppress UndefinedVariable
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function createController($route, $owner = null)
    {
        if ($owner === null) {
            $owner = $this;
        }
        if ((array) $route === $route || ($route = trim($route, '/')) === '') {
            $route = $owner->defaultController;
        }
        $caseSensitive = $this->getUrlManager()->caseSensitive;

        $route .= '/';
        while (($pos = strpos($route, '/')) !== false) {
            $id = substr($route, 0, $pos);
            if (! preg_match('/^\w+$/', $id)) {
                return null;
            }
            if (! $caseSensitive) {
                $id = strtolower($id);
            }
            $route = (string) substr($route, $pos + 1);
            if (! isset($basePath)) {  // first segment
                if (isset($owner->controllerMap[$id])) {
                    return [
                        \Yii::createComponent($owner->controllerMap[$id], $id, $owner === $this ? null : $owner),
                        $this->parseActionParams($route),
                    ];
                }

                if (($module = $owner->getModule($id)) !== null) {
                    return $this->createController($route, $module);
                }
                $basePath = $owner->getControllerPath();
                $controllerID = '';
            } else {
                $controllerID .= '/';
            }
            $className = ucfirst($id) . 'Controller';
            $classFile = $basePath . DIRECTORY_SEPARATOR . $className . '.php';

            if ($owner->controllerNamespace !== null) {
                $className = $owner->controllerNamespace . '\\' . str_replace('/', '\\', $controllerID) . $className;
            }

            if (is_file($classFile)) {
                if (! class_exists($className, false)) {
                    include $classFile;
                }
                if (class_exists($className, false) && is_subclass_of($className, 'CController')) {
                    $id[0] = strtolower($id[0]);

                    return [
                        $this->newInstance($className, $controllerID . $id, $owner === $this ? null : $owner),
                        $this->parseActionParams($route),
                    ];
                }

                return null;
            }
            $controllerID .= $id;
            $basePath .= DIRECTORY_SEPARATOR . $id;
        }
    }

    private function newInstance(string $className, $controllerId, $owner)
    {
        $isInjectable = in_array(Injectable::class, class_implements($className), true);
        $controller = $isInjectable ? \Yii::getInjector()->getInstanceWithArgs($className, '', [$controllerId, $owner]) : new $className($controllerId, $owner);

        return $controller;
    }
}
