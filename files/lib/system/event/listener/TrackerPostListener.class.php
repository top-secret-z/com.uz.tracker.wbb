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
use wbb\data\post\PostAction;
use wbb\data\thread\Thread;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to post action.
 */
class TrackerPostListener implements IParameterizedEventListener
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
        if (!$this->tracker->wlwbbPost && !$this->tracker->otherModeration) {
            return;
        }

        // post
        if ($eventObj instanceof PostAction) {
            $action = $eventObj->getActionName();

            if ($action == 'quickReply') {
                $returnValues = $eventObj->getReturnValues();
                $post = new Post($returnValues['returnValues']['objectID']);

                // disregard first post, since it is covered by thread
                if ($post->isFirstPost()) {
                    return;
                }

                $this->link = $post->getLink();

                // normal post
                if ($this->tracker->wlwbbPost) {
                    if ($post->isDisabled) {
                        $this->store('wcf.uztracker.description.post.addDisabled', 'wcf.uztracker.type.wlwbb');
                    } else {
                        $this->store('wcf.uztracker.description.post.add', 'wcf.uztracker.type.wlwbb');
                    }
                }
            }

            if ($this->tracker->otherModeration) {
                if ($action == 'disable' || $action == 'enable' || $action == 'setEnableTime') {
                    $objects = $eventObj->getObjects();
                    foreach ($objects as $post) {
                        $this->link = $post->getLink();
                        if ($action == 'disable') {
                            $this->store('wcf.uztracker.description.post.disable', 'wcf.uztracker.type.moderation');
                        } elseif ($action == 'setEnableTime') {
                            $this->store('wcf.uztracker.description.post.enable.time', 'wcf.uztracker.type.moderation');
                        } else {
                            $this->store('wcf.uztracker.description.post.enable', 'wcf.uztracker.type.moderation');
                        }
                    }
                }

                if ($action == 'deleteCompletely') {
                    $objects = $eventObj->getObjects();
                    foreach ($objects as $post) {
                        $this->link = '';
                        $name = $post->getTitle();
                        $content = $post->message;
                        $this->store('wcf.uztracker.description.post.delete', 'wcf.uztracker.type.moderation', $name, $content);
                    }
                }

                if ($action == 'close' || $action == 'open') {
                    $objects = $eventObj->getObjects();
                    foreach ($objects as $post) {
                        $this->link = $post->getLink();
                        if ($action == 'close') {
                            $this->store('wcf.uztracker.description.post.close', 'wcf.uztracker.type.moderation');
                        } else {
                            $this->store('wcf.uztracker.description.post.open', 'wcf.uztracker.type.moderation');
                        }
                    }
                }

                if ($action == 'moveToExistingThread' || $action == 'moveToNewThread') {
                    $objects = $eventObj->getObjects();
                    foreach ($objects as $post) {
                        $this->link = $post->getLink();
                        $this->store('wcf.uztracker.description.post.move', 'wcf.uztracker.type.moderation');
                    }
                }

                if ($action == 'merge') {
                    $returnValues = $eventObj->getReturnValues();
                    $this->link = $returnValues['returnValues']['redirectURL'];
                    $this->store('wcf.uztracker.description.post.merge', 'wcf.uztracker.type.moderation');
                }

                if ($action == 'copyToExistingThread' || $action == 'copyToNewThread') {
                    $params = $eventObj->getParameters();
                    $thread = new Thread($params['threadID']);

                    if (!$thread->threadID) {
                        $this->link = '';
                        $this->store('wcf.uztracker.description.post.copy.progress', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->link = $thread->getLink();
                        $this->store('wcf.uztracker.description.post.copy', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'trash' || $action == 'restore') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $post) {
                    $this->link = $post->getLink();
                    if ($action == 'trash') {
                        if ($post->userID == $user->userID) {
                            if ($this->tracker->wlwbbPost) {
                                $this->store('wcf.uztracker.description.post.trash', 'wcf.uztracker.type.wlwbb');
                            }
                        } else {
                            if ($this->tracker->otherModeration) {
                                $this->store('wcf.uztracker.description.post.trash', 'wcf.uztracker.type.moderation');
                            }
                        }
                    } else {
                        if ($post->userID == $user->userID) {
                            if ($this->tracker->wlwbbPost) {
                                $this->store('wcf.uztracker.description.post.restore', 'wcf.uztracker.type.wlwbb');
                            }
                        } else {
                            if ($this->tracker->otherModeration) {
                                $this->store('wcf.uztracker.description.post.restore', 'wcf.uztracker.type.moderation');
                            }
                        }
                    }
                }
            }

            if ($action == 'update') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $post) {
                    $this->link = $post->getLink();
                    if ($post->userID == $user->userID) {
                        if ($this->tracker->wlwbbPost) {
                            $this->store('wcf.uztracker.description.post.update', 'wcf.uztracker.type.wlwbb');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.post.update', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }
    }

    /**
     * store log entry
     */
    protected function store($description, $type, $name = '', $content = '')
    {
        $packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.wbb');
        TrackerLogEditor::create([
            'description' => $description,
            'link' => $this->link,
            'name' => $name,
            'trackerID' => $this->tracker->trackerID,
            'type' => $type,
            'packageID' => $packageID,
            'content' => $content,
        ]);
    }
}
