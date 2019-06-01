<?php
namespace BGMManager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class BGMManager extends PluginBase implements Listener {

    private static $instance = null;
    public $bgm = [];

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->user = new Config($this->getDataFolder() . "user.yml", Config::YAML);
        $this->udata = $this->user->getAll();
    }

    public function onDisable() {
        $this->user->setAll($this->udata);
        $this->user->save();
    }

    public function setBgmPlaying(Player $player, $play) {
        if (isset($this->bgm[$player->getName()])) {
            $this->getScheduler()->cancelTask($this->bgm[$player->getName()]);
            unset($this->bgm[$player->getName()]);
        }
        if ($play == true) {
            $this->udata[$player->getName()]["bgm"] = true;
            $this->getScheduler()->scheduleRepeatingTask(
                    new class($this, $player) extends Task {
                        public function __construct(BGMManager $plugin, Player $player) {
                            $this->plugin = $plugin;
                            $this->player = $player;
                        }

                        public function onRun($currentTick) {
                            if (!$this->player instanceof Player) {
                                $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                                unset($this->plugin->bgm[$this->player->getName()]);
                            } else {
                                $this->plugin->endBGM($this->player);
                                $this->plugin->startBGM($this->player);
                                $this->plugin->bgm[$this->player->getName()] = $this->getTaskId();
                            }
                        }
                    }, 100 * 20);
        } else {
            $this->udata[$player->getName()]["bgm"] = false;
            $this->endBGM($player);
        }
    }

    public function endBGM(Player $player) {
        $pk = new StopSoundPacket;
        $pk->soundName = "portal.travel";
        $pk->stopAll = true;
        $player->dataPacket($pk);
    }

    public function startBGM(Player $player) {
        $pk = new PlaySoundPacket();
        $pk->soundName = "portal.travel";
        $pk->x = $player->getX();
        $pk->y = $player->getY();
        $pk->z = $player->getZ();
        $pk->volume = 100;
        $pk->pitch = 1;
        $player->dataPacket($pk);
    }

    public function onJoin(PlayerJoinEvent $ev) {
        if (!isset($this->udata[$ev->getPlayer()->getName()]["bgm"]))
            $this->udata[$ev->getPlayer()->getName()]["bgm"] = true;
        if ($this->udata[$ev->getPlayer()->getName()]["bgm"] == true) {
            $this->getScheduler()->scheduleRepeatingTask(
                    new class($this, $ev->getPlayer()) extends Task {
                        public function __construct(BGMManager $plugin, Player $player) {
                            $this->plugin = $plugin;
                            $this->player = $player;
                        }

                        public function onRun($currentTick) {
                            if (!$this->player instanceof Player) {
                                $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                                unset($this->plugin->bgm[$this->player->getName()]);
                            } else {
                                $this->plugin->bgm[$this->player->getName()] = $this->getTaskId();
                                $this->plugin->endBGM($this->player);
                                $this->plugin->startBGM($this->player);
                            }
                        }
                    }, 100 * 20);
        }
    }

    public function onQuit(PlayerQuitEvent $ev) {
        if (isset($this->bgm[$ev->getPlayer()->getName()])) {
            $this->getScheduler()->cancelTask($this->bgm[$ev->getPlayer()->getName()]);
            unset($this->bgm[$ev->getPlayer()->getName()]);
        }
    }
}
