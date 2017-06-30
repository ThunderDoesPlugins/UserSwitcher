<?php
/* Made By Thunder33345 */

namespace Thunder33345\UserSwitcher;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class DelayTransferTask extends PluginTask
{
  private $loader,$server;
  private $from,$to;

  public function __construct(UserSwitcher $loader,$from,$to)
  {
    parent::__construct($loader);
    $this->loader = $loader;
    $this->server = $loader->getServer();
    $this->from = $from;
    $this->to = $to;
  }

  public function onRun($currentTick)
  {
    if($this->from instanceof Player) $player = $this->from; else $player = $this->loader->getServer()->getPlayer($this->from);
    if($player instanceof Player) $this->loader->switchUser($player,$this->to);

  }
}