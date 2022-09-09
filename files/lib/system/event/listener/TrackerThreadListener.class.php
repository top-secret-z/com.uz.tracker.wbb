<?php
namespace wbb\system\event\listener;
use wbb\data\post\Post;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\WCF;

/**
 * Listen to thread action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.wbb
 */
class TrackerThreadListener implements IParameterizedEventListener {
	/**
	 * tracker and link
	 */
	protected $tracker = null;
	protected $link = '';
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_TRACKER) return;
		
		// only if user is to be tracked
		$user = WCF::getUser();
		if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) return;
		
		// only if trackers
		$trackers = TrackerCacheBuilder::getInstance()->getData();
		if (!isset($trackers[$user->userID])) return;
		
		$this->tracker = $trackers[$user->userID];
		if (!$this->tracker->wlwbbThread && !$this->tracker->otherModeration) return;
		
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
				}
				else {
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
					}
					else {
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
					}
					else {
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
					}
					else {
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
						if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.trash', 'wcf.uztracker.type.wlwbb');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.thread.trash', 'wcf.uztracker.type.moderation');
					}
				}
				else {
					if ($thread->userID == $user->userID) {
						if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.restore', 'wcf.uztracker.type.wlwbb');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.thread.restore', 'wcf.uztracker.type.moderation');
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
						if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.done', 'wcf.uztracker.type.wlwbb');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.thread.done', 'wcf.uztracker.type.moderation');
					}
				}
				else {
					if ($thread->userID == $user->userID) {
						if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.undone', 'wcf.uztracker.type.wlwbb');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.thread.undone', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		if ($action == 'update') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $thread) {
				$this->link = $thread->getLink();
				if ($thread->userID == $user->userID) {
					if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.update', 'wcf.uztracker.type.wlwbb');
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.thread.update', 'wcf.uztracker.type.moderation');
				}
			}
		}
		
		// since 5.2
		if ($action == 'markAsBestAnswer') {
			$params = $eventObj->getParameters();
			$post = new post($params['postID']);
			if ($post->postID) {
				$this->link = $post->getLink();
				if ($this->tracker->wlwbbThread) $this->store('wcf.uztracker.description.thread.bestAnswer', 'wcf.uztracker.type.wlwbb');
			}
		}
	}
	
	/**
	 * store log entry
	 */
	protected function store ($description, $type, $name = '') {
		$packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.wbb');
		TrackerLogEditor::create(array(
				'description' => $description,
				'link' => $this->link,
				'name' => $name,
				'trackerID' => $this->tracker->trackerID,
				'type' => $type,
				'packageID' => $packageID
		));
	}
}
