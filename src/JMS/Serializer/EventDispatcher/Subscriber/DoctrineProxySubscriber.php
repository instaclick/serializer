<?php

/*
 * Copyright 2013 Johannes M. Schmitt <schmittjoh@gmail.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer\EventDispatcher\Subscriber;

use Doctrine\ORM\PersistentCollection;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\Proxy\Proxy as ORMProxy;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;

class DoctrineProxySubscriber implements EventSubscriberInterface
{
    public function onPreSerialize(PreSerializeEvent $event)
    {
        $object = $event->getObject();
        $type   = $event->getType();

        // If the set type name is not an actual class, but a faked type for which a custom handler exists, we do not
        // modify it with this subscriber. Also, we forgo autoloading here as an instance of this type is already created,
        // so it must be loaded if its a real class.
        $virtualType = ! class_exists($type['name'], false);

        if ($object instanceof PersistentCollection) {
            if ( ! $virtualType) {
                $event->setType('ArrayCollection');
            }

            return;
        }

        if ( ! $object instanceof Proxy && ! $object instanceof ORMProxy) {
            try {
                $class = new \ReflectionClass($type['name']);

                if ($class->isInterface()) {
                    $event->setType(get_class($object));
                }
            } catch (\ReflectionException $e) {}

            return;
        }

        $object->__load();

        if ( ! $virtualType) {
            $event->setType(get_parent_class($object));
        }

        // Handle the interface class when the object is an instance of either Proxy or ORMProxy.
        try {
            $class = new \ReflectionClass($type['name']);

            if ($class->isInterface()) {
                $event->setType(get_class($object));
            }
        } catch (\ReflectionException $e) {
            // NOP
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            array('event' => 'serializer.pre_serialize', 'method' => 'onPreSerialize'),
        );
    }
}
