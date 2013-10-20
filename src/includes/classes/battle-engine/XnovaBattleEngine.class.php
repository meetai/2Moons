<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Jan
 * Date: 08.10.13
 * Time: 10:35
 * To change this template use File | Settings | File Templates.
 */

class XnovaBattleEngine implements BattleEngineInterface
{
	private $config;
	private $data;
	private $round;

	private $result = array();

	public function setConfig($config)
	{
		$this->config	= $config;
	}

	public function setData($data)
	{
		$this->data		= $data;
	}

	public function getResult()
	{
		$this->result;
	}

	private function _saveCurrentFleet()
	{
		$this->result[$this->round]['currentFleets']	= $this->data['fleet'];
	}

	private function _attack($attacker, $defender)
	{
		foreach($this->data['fleet'][$attacker] as $fleetId => $fleetData)
		{
			$this->result[$this->round]['defender']['damage'][$fleetId] = 0;
			$this->result[$this->round]['defender']['shield'][$fleetId] = 0;
			$this->result[$this->round]['defender']['amount'][$fleetId] = 0;

			foreach ($defender['unit'] as $element => $amount) {
				$thisAtt	= $amount * ($CombatCaps[$element]['attack']) * $attTech * (rand(80, 120) / 100); //attaque
				$thisDef	= $amount * ($CombatCaps[$element]['shield']) * $defTech ; //bouclier
				$thisShield	= $amount * ($pricelist[$element]['cost'][901] + $pricelist[$element]['cost'][902]) / 10 * $shieldTech; //coque

				if ($element == 407 || $element == 408 || $element == 409) $thisAtt = 0;

				$defArray[$fleetId][$element] = array('def' => $thisDef, 'shield' => $thisShield, 'att' => $thisAtt);

				$defenseDamage[$fleetId] += $thisAtt;
				$defenseDamage['total'] += $thisAtt;
				$defenseShield[$fleetId] += $thisDef;
				$defenseShield['total'] += $thisDef;
				$defenseAmount[$fleetId] += $amount;
				$defenseAmount['total'] += $amount;
			}
		}
	}

	public function simulate()
	{
		for ($this->round = 1; $this->round <= $this->config['maxRounds']; $this->round++)
		{
			$this->_saveCurrentFleet();

			$attackDamage  = array('total' => 0);
			$attackShield  = array('total' => 0);
			$attackAmount  = array('total' => 0);
			$defenseDamage = array('total' => 0);
			$defenseShield = array('total' => 0);
			$defenseAmount = array('total' => 0);

			$attArray = array();
			$defArray = array();

			$this->_attack('attacker', 'defender');

			foreach ($defenders as $fleetId => $defender) {
				$defenseDamage[$fleetId] = 0;
				$defenseShield[$fleetId] = 0;
				$defenseAmount[$fleetId] = 0;

				$attTech	= (1 + (0.1 * $defender['player']['military_tech']) + $defender['player']['factor']['Attack']); //attaquue
				$defTech	= (1 + (0.1 * $defender['player']['defence_tech']) + $defender['player']['factor']['Defensive']); //bouclier
				$shieldTech = (1 + (0.1 * $defender['player']['shield_tech']) + $defender['player']['factor']['Shield']); //coque
				$defenders[$fleetId]['techs'] = array($attTech, $defTech, $shieldTech);

				foreach ($defender['unit'] as $element => $amount) {
					$thisAtt	= $amount * ($CombatCaps[$element]['attack']) * $attTech * (rand(80, 120) / 100); //attaque
					$thisDef	= $amount * ($CombatCaps[$element]['shield']) * $defTech ; //bouclier
					$thisShield	= $amount * ($pricelist[$element]['cost'][901] + $pricelist[$element]['cost'][902]) / 10 * $shieldTech; //coque

					if ($element == 407 || $element == 408 || $element == 409) $thisAtt = 0;

					$defArray[$fleetId][$element] = array('def' => $thisDef, 'shield' => $thisShield, 'att' => $thisAtt);

					$defenseDamage[$fleetId] += $thisAtt;
					$defenseDamage['total'] += $thisAtt;
					$defenseShield[$fleetId] += $thisDef;
					$defenseShield['total'] += $thisDef;
					$defenseAmount[$fleetId] += $amount;
					$defenseAmount['total'] += $amount;
				}
			}

			$ROUND[$ROUNDC] = array('attackers' => $attackers, 'defenders' => $defenders, 'attackA' => $attackAmount, 'defenseA' => $defenseAmount, 'infoA' => $attArray, 'infoD' => $defArray);

			if ($ROUNDC >= MAX_ATTACK_ROUNDS || $defenseAmount['total'] <= 0 || $attackAmount['total'] <= 0) {
				break;
			}

			// Calculate hit percentages (ACS only but ok)
			$attackPct = array();
			foreach ($attackAmount as $fleetId => $amount) {
				if (!is_numeric($fleetId)) continue;
				$attackPct[$fleetId] = $amount / $attackAmount['total'];
			}

			$defensePct = array();
			foreach ($defenseAmount as $fleetId => $amount) {
				if (!is_numeric($fleetId)) continue;
				$defensePct[$fleetId] = $amount / $defenseAmount['total'];
			}

			// CALCUL DES PERTES !!!
			$attacker_n = array();
			$attacker_shield = 0;
			$defenderAttack	= 0;
			foreach ($attackers as $fleetId => $attacker) {
				$attacker_n[$fleetId] = array();

				foreach($attacker['unit'] as $element => $amount) {
					if ($amount <= 0) {
						$attacker_n[$fleetId][$element] = 0;
						continue;
					}

					$defender_moc = $amount * ($defenseDamage['total'] * $attackPct[$fleetId]) / $attackAmount[$fleetId];

					if(isset($RF[$element])) {
						foreach($RF[$element] as $shooter => $shots) {
							foreach($defArray as $fID => $rfdef) {
								if(empty($rfdef[$shooter]['att']) || $attackAmount[$fleetId] <= 0) continue;

								$defender_moc += $rfdef[$shooter]['att'] * $shots / ($amount / $attackAmount[$fleetId] * $attackPct[$fleetId]);
								$defenseAmount['total'] += $defenders[$fID]['unit'][$shooter] * $shots;
							}
						}
					}

					$defenderAttack	+= $defender_moc;

					if (($attArray[$fleetId][$element]['def'] / $amount) >= $defender_moc) {
						$attacker_n[$fleetId][$element] = round($amount);
						$attacker_shield += $defender_moc;
						continue;
					}

					$max_removePoints = floor($amount * $defenseAmount['total'] / $attackAmount[$fleetId] * $attackPct[$fleetId]);

					$attacker_shield += min($attArray[$fleetId][$element]['def'], $defender_moc);
					$defender_moc 	 -= min($attArray[$fleetId][$element]['def'], $defender_moc);

					$ile_removePoints = max(min($max_removePoints, $amount * min($defender_moc / $attArray[$fleetId][$element]['shield'] * (rand(0, 200) / 100), 1)), 0);

					$attacker_n[$fleetId][$element] = max(ceil($amount - $ile_removePoints), 0);
				}
			}

			$defender_n = array();
			$defender_shield = 0;
			$attackerAttack	= 0;
			foreach ($defenders as $fleetId => $defender) {
				$defender_n[$fleetId] = array();

				foreach($defender['unit'] as $element => $amount) {
					if ($amount <= 0) {
						$defender_n[$fleetId][$element] = 0;
						continue;
					}

					$attacker_moc = $amount * ($attackDamage['total'] * $defensePct[$fleetId]) / $defenseAmount[$fleetId];
					if (isset($RF[$element])) {
						foreach($RF[$element] as $shooter => $shots) {
							foreach($attArray as $fID => $rfatt) {
								if (empty($rfatt[$shooter]['att']) || $defenseAmount[$fleetId] <= 0 ) continue;

								$attacker_moc += $rfatt[$shooter]['att'] * $shots / ($amount / $defenseAmount[$fleetId] * $defensePct[$fleetId]);
								$attackAmount['total'] += $attackers[$fID]['unit'][$shooter] * $shots;
							}
						}
					}

					$attackerAttack	+= $attacker_moc;

					if (($defArray[$fleetId][$element]['def'] / $amount) >= $attacker_moc) {
						$defender_n[$fleetId][$element] = round($amount);
						$defender_shield += $attacker_moc;
						continue;
					}

					$max_removePoints = floor($amount * $attackAmount['total'] / $defenseAmount[$fleetId] * $defensePct[$fleetId]);
					$defender_shield += min($defArray[$fleetId][$element]['def'], $attacker_moc);
					$attacker_moc 	 -= min($defArray[$fleetId][$element]['def'], $attacker_moc);

					$ile_removePoints = max(min($max_removePoints, $amount * min($attacker_moc / $defArray[$fleetId][$element]['shield'] * (rand(0, 200) / 100), 1)), 0);

					$defender_n[$fleetId][$element] = max(ceil($amount - $ile_removePoints), 0);
				}
			}

			$ROUND[$ROUNDC]['attack'] 		= $attackerAttack;
			$ROUND[$ROUNDC]['defense'] 		= $defenderAttack;
			$ROUND[$ROUNDC]['attackShield'] = $attacker_shield;
			$ROUND[$ROUNDC]['defShield'] 	= $defender_shield;
			foreach ($attackers as $fleetId => $attacker) {
				$attackers[$fleetId]['unit'] = array_map('round', $attacker_n[$fleetId]);
			}

			foreach ($defenders as $fleetId => $defender) {
				$defenders[$fleetId]['unit'] = array_map('round', $defender_n[$fleetId]);
			}
		}

		if ($attackAmount['total'] <= 0 && $defenseAmount['total'] > 0) {
			$won = "r"; // defender
		} elseif ($attackAmount['total'] > 0 && $defenseAmount['total'] <= 0) {
			$won = "a"; // attacker
		} else {
			$won = "w"; // draw
		}

		// CDR
		foreach ($attackers as $fleetId => $attacker) {					   // flotte attaquant en CDR
			foreach ($attacker['unit'] as $element => $amount) {
				$TRES['attacker'] -= $pricelist[$element]['cost'][901] * $amount ;
				$TRES['attacker'] -= $pricelist[$element]['cost'][902] * $amount ;

				$ARES['metal'] -= $pricelist[$element]['cost'][901] * $amount ;
				$ARES['crystal'] -= $pricelist[$element]['cost'][902] * $amount ;
			}
		}

		$DRESDefs = array('metal' => 0, 'crystal' => 0);

		foreach ($defenders as $fleetId => $defender) {
			foreach ($defender['unit'] as $element => $amount) {
				if ($element < 300) {							// flotte defenseur en CDR
					$DRES['metal'] 	 -= $pricelist[$element]['cost'][901] * $amount ;
					$DRES['crystal'] -= $pricelist[$element]['cost'][902] * $amount ;

					$TRES['defender'] -= $pricelist[$element]['cost'][901] * $amount ;
					$TRES['defender'] -= $pricelist[$element]['cost'][902] * $amount ;
				} else {									// defs defenseur en CDR + reconstruction
					$TRES['defender'] -= $pricelist[$element]['cost'][901] * $amount ;
					$TRES['defender'] -= $pricelist[$element]['cost'][902] * $amount ;

					$lost = $STARTDEF[$element] - $amount;
					$giveback = round($lost * (rand(56, 84) / 100));
					$defenders[$fleetId]['unit'][$element] += $giveback;
					$DRESDefs['metal'] 	 += $pricelist[$element]['cost'][901] * ($lost - $giveback) ;
					$DRESDefs['crystal'] += $pricelist[$element]['cost'][902] * ($lost - $giveback) ;
				}
			}
		}

		$ARES['metal']		= max($ARES['metal'], 0);
		$ARES['crystal']	= max($ARES['crystal'], 0);
		$DRES['metal']		= max($DRES['metal'], 0);
		$DRES['crystal']	= max($DRES['crystal'], 0);
		$TRES['attacker']	= max($TRES['attacker'], 0);
		$TRES['defender']	= max($TRES['defender'], 0);

		$totalLost = array('attacker' => $TRES['attacker'], 'defender' => $TRES['defender']);
		$debAttMet = ($ARES['metal'] * ($FleetTF / 100));
		$debAttCry = ($ARES['crystal'] * ($FleetTF / 100));
		$debDefMet = ($DRES['metal'] * ($FleetTF / 100)) + ($DRESDefs['metal'] * ($DefTF / 100));
		$debDefCry = ($DRES['crystal'] * ($FleetTF / 100)) + ($DRESDefs['crystal'] * ($DefTF / 100));

		return array('won' => $won, 'debris' => array('attacker' => array(901 => $debAttMet, 902 => $debAttCry), 'defender' => array(901 => $debDefMet, 902 => $debDefCry)), 'rw' => $ROUND, 'unitLost' => $totalLost);
	}
}