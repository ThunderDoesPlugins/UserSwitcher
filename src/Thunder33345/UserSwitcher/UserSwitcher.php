<?php
/** Created By Thunder33345 **/

namespace Thunder33345\UserSwitcher;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class UserSwitcher extends PluginBase implements Listener
{
  private $pending = [];
  private $alias = [];
  private $ip = [];

  private $customTransfer = false;
  private $alwaysSwitch = [];

  public function onLoad()
  {

  }

  public function onEnable()
  {
    $this->saveDefaultConfig();
    $this->customTransfer = $this->getConfig()->get('use-custom-transfer',false);
    $this->alwaysSwitch = $this->getConfig()->get('always-switch',[]);

    $this->getServer()->getPluginManager()->registerEvents($this,$this);
  }

  public function onDisable()
  {

  }

  public function onCommand(CommandSender $sender,Command $command,$label,array $args)
  {
    if(!$sender instanceof Player OR !isset($args[0])) {
      $sender->sendMessage('Only Player Can Switch User');
      return;
    }
    if(!$sender->hasPermission('userswitcher.use')) {
      $sender->sendMessage('Insufficient Permission');
      return;
    }
    $sender->sendMessage("Switching user...");
    $this->switchUser($sender,$args[0]);
  }

  public function onReceivePacket(DataPacketReceiveEvent $event)
  {
    $pk = $event->getPacket();
    if($pk->pid() !== ProtocolInfo::LOGIN_PACKET OR !$pk instanceof LoginPacket) return;

    $ip = $event->getPlayer()->getAddress();
    $connectIp = explode(':',$pk->serverAddress);
    $this->ip[$ip] = $connectIp;

    $name = $pk->username;
    if($this->getPending($name) !==false) {
      $nameTo = $this->getPending($name);
      unset($this->pending[$name]);

      $pk->username = $nameTo;
      $this->alias[$nameTo] = $name;
    }
  }

  public function preLogin(PlayerPreLoginEvent $event)
  {
    $player = $event->getPlayer();
    $name = $player->getName();
    if(isset($this->alwaysSwitch[$name])) {//because i cant use this in packets receive nor onjoin
      $to = $this->alwaysSwitch[$name];
      $this->switchUser($player,$to);
      $event->setCancelled(true);
    }
  }

  /*  public function firstSpawnEvent(PlayerRespawnEvent $event)
    {
      $player = $event->getPlayer();
      $name = $player->getName();

      if(isset($this->firstSpawn[$name])) return;

      $this->firstSpawn[$name] = true;
      if(isset($this->alwaysSwitch[$name])) {//because i cant use this in packets receive nor onjoin
        $to = $this->alwaysSwitch[$name];
        $this->delayedSwitchUser($player,$to,5);
      }
    }*/

  public function onLeft(PlayerQuitEvent $event)
  {
    $name = $event->getPlayer()->getName();
    $ip = $event->getPlayer()->getAddress();
    unset($this->alias[$name],$this->ip[$ip]);
  }

  /**
   * Public API to switch user
   * @param Player $player
   * @param string $to
   */
  public function switchUser(Player $player,string $to)
  {
    $name = $player->getName();

    if(isset($this->alias[$name])) $name = $this->alias[$name];

    $this->setPending($name,$to);

    $ip = $this->ip[$player->getAddress()];
    $this->transfer($player,$ip[0],$ip[1]);
  }

  /**
   * This is a delayed transfer
   * only useful if immediate transfer will result in errors
   * @param string|Player $from Username to be transfer
   * @param string $to To what username
   * @param int $delay To wait how long?(ticks)
   * No error handling given here, you may have to check client if client secret is the same to tell if it has been transfer
   */
  public function delayedSwitchUser($from,string $to,int $delay)
  {
    $this->getServer()->getScheduler()->scheduleDelayedTask(new DelayTransferTask($this,$from,$to),$delay);
  }

  private function transfer(Player $player,$ip,$port)
  {
    if($this->customTransfer) {
      $pk = new TransferPacket();
      $pk->address = $ip;
      $pk->port = $port;
      $player->directDataPacket($pk);
      $player->close("","transfer",false);
    } else $player->transfer($ip,$port);
  }

  private function setPending($from,$to) {$this->pending[$from] = $to;}

  public function getPending($of) {if(isset($this->pending[$of])) return $this->pending[$of]; else return false;  }
}