<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace wbb\system\event\listener;

use wbb\data\post\Post;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to thread action.
 */
class TrackerThreadListener implements IParameterizedEventListener
{
    /**
     * tracker and link
     */
    protected $tracker;

    protected $link = '';

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (!MODULE_TRACKER) {
            return;
        }

        // only if user is to be tracked
        $user = WCF::getUser();
        if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) {
            return;
        }

        // only if trackers
        $trackers = TrackerCacheBuilder::getInstance()->getData();
        if (!isset($trackers[$user->userID])) {
            return;
        }

        $this->tracker = $trackers[$user->userID];
        if (!$this->tracker->wlwbbThread && !$this->tracker->otherModeration) {
            return;
        }

        // actions / data
        $action = $eventObj->getActionName();

        if ($this->tracker->wlwbbThread) {
            // create, don't care about publication
            if ($action == 'create') {
                $returnValues = $eventObj->getReturnValues();
                $thread = $returnValues['returnValues'];
                $this->link = $thread->getLink();
                if ($thread->isDisabled) {
                    $this->store('wcf.uztracker.description.thread.addDisabled', 'wcf.uztracker.type.wlwbb');
                } else {
                    $this->store('wcf.uztracker.description.thread.add', 'wcf.uztracker.type.wlwbb');
                }
            }
        }

        if ($this->tracker->otherModeration) {
            if ($action == 'disable' || $action == 'enable') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $thread) {
                    $this->link = $thread->getLink();
                    if ($action == 'disable') {
                        $this->store('wcf.uztracker.description.thread.disable', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.thread.enable', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'close' || $action == 'open') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $thread) {
                    $this->link = $thread->getLink();
                    if ($action == 'close') {
                        $this->store('wcf.uztracker.description.thread.close', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.thread.open', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'delete') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $thread) {
                    $this->link = '';
                    $name = $thread->topic;
                    $this->store('wcf.uztracker.description.thread.delete', 'wcf.uztracker.type.moderation', $name);
                }
            }

            if ($action == 'move') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $thread) {
                    $this->link = $thread->getLink();
                    $this->store('wcf.uztracker.description.thread.move', 'wcf.uztracker.type.moderation');
                }
            }

            if ($action == 'scrape' || $action == 'sticky') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $thread) {
                    $this->link = $thread->getLink();
                    if ($action == 'scrape') {
                        $this->store('wcf.uztracker.description.thread.scrape', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.thread.sticky', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'merge') {
                $returnValues = $eventObj->getReturnValues();
                if (isset($returnValues['returnValues']['redirectURL'])) {
                    $this->link = $returnValues['returnValues']['redirectURL'];
                    $this->store('wcf.uztracker.description.thread.merge', 'wcf.uztracker.type.moderation');
                }
            }
        }

        if ($action == 'trash' || $action == 'restore') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $thread) {
                $this->link = $thread->getLink();
                if ($action == 'trash') {
                    if ($thread->userID == $user->userID) {
                        if ($this->tracker->wlwbbThread) {
                            $this->store('wcf.uztracker.description.thread.trash', 'wcf.uztracker.type.wlwbb');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.thread.trash', 'wcf.uztracker.type.moderation');
                        }
                    }
                } else {
                    if ($thread->userID == $user->userID) {
                        if ($this->tracker->wlwbbThread) {
                            $this->store('wcf.uztracker.description.thread.restore', 'wcf.uztracker.type.wlwbb');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.thread.restore', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }

        if ($action == 'done' || $action == 'undone') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $thread) {
                $this->link = $thread->getLink();
                if ($action == 'done') {
                    if ($thread->userID == $user->userID) {
                        if ($this->tracker->wlwbbThread) {
                            $this->store('wcf.uztracker.description.thread.done', 'wcf.uztracker.type.wlwbb');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.thread.done', 'wcf.uztracker.type.moderation');
                        }
                    }
                } else {
                    if ($thread->userID == $user->userID) {
                        if ($this->tracker->wlwbbThread) {
                            $this->store('wcf.uztracker.description.thread.undone', 'wcf.uztracker.type.wlwbb');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.thread.undone', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }

        if ($action == 'update') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $thread) {
                $this->link = $thread->getLink();
                if ($thread->userID == $user->userID) {
                    if ($this->tracker->wlwbbThread) {
                        $this->store('wcf.uztracker.description.thread.update', 'wcf.uztracker.type.wlwbb');
                    }
                } else {
                    if ($this->tracker->otherModeration) {
                        $this->store('wcf.uztracker.description.thread.update', 'wcf.uztracker.type.moderation');
                    }
                }
            }
        }

        // since 5.2
        if ($action == 'markAsBestAnswer') {
            $params = $eventObj->getParameters();
            $post = new post($params['postID']);
            if ($post->postID) {
                $this->link = $post->getLink();
                if ($this->tracker->wlwbbThread) {
                    $this->store('wcf.uztracker.description.thread.bestAnswer', 'wcf.uztracker.type.wlwbb');
                }
            }
        }
    }

    /**
     * store log entry
     */
    protected function store($description, $type, $name = '')
    {
        $packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.wbb');
        TrackerLogEditor::create([
            'description' => $description,
            'link' => $this->link,
            'name' => $name,
            'trackerID' => $this->tracker->trackerID,
            'type' => $type,
            'packageID' => $packageID,
        ]);
    }
}
