<?php
declare(strict_types=1);

namespace App\Helpers;

use Stringable;

/**
 * Helper class to handle phone numbers and their prefixes.
 * It can extract the prefix and the country associated with it.
 * The prefixes are defined in the PREFIXES constant.
 */
final class PhoneNumber implements Stringable
{

	/** @var array<int, array<int|string, array<int|string, array<int, array<int, array<int, array<int, array<int, string>>|string>>|string>|string>|string>> List of all country prefixes stored in a radix tree */
	public final const array PREFIXES = [
		1 =>
			[
				99  => 'US / USA',
				'-' =>
					[
						6 =>
							[
								8 =>
									[
										4 =>
											[
												99 => 'AS / ASM',
											],
									],
								7 =>
									[
										1 =>
											[
												99 => 'GU / GUM',
											],
										0 =>
											[
												99 => 'MP / MNP',
											],
									],
								6 =>
									[
										4 =>
											[
												99 => 'MS / MSR',
											],
									],
								4 =>
									[
										9 =>
											[
												99 => 'TC / TCA',
											],
									],
							],
						2 =>
							[
								6 =>
									[
										4 =>
											[
												99 => 'AI / AIA',
											],
										8 =>
											[
												99 => 'AG / ATG',
											],
									],
								4 =>
									[
										2 =>
											[
												99 => 'BS / BHS',
											],
										6 =>
											[
												99 => 'BB / BRB',
											],
									],
								8 =>
									[
										4 =>
											[
												99 => 'VG / VGB',
											],
									],
							],
						4 =>
							[
								4 =>
									[
										1 =>
											[
												99 => 'BM / BMU',
											],
									],
								7 =>
									[
										3 =>
											[
												99 => 'GD / GRD',
											],
									],
							],
						3 =>
							[
								4 =>
									[
										5 =>
											[
												99 => 'KY / CYM',
											],
										0 =>
											[
												99 => 'VI / VIR',
											],
									],
							],
						7 =>
							[
								6 =>
									[
										7 =>
											[
												99 => 'DM / DMA',
											],
									],
								8 =>
									[
										7 =>
											[
												99 => 'PR / PRI',
											],
										4 =>
											[
												99 => 'VC / VCT',
											],
									],
								5 =>
									[
										8 =>
											[
												99 => 'LC / LCA',
											],
									],
								2 =>
									[
										1 =>
											[
												99 => 'SX / SXM',
											],
									],
							],
						8 =>
							[
								0 =>
									[
										9 =>
											[
												99 => 'DO / DOM',
											],
									],
								2 =>
									[
										9 =>
											[
												99 => 'DO / DOM',
											],
									],
								4 =>
									[
										9 =>
											[
												99 => 'DO / DOM',
											],
									],
								7 =>
									[
										6 =>
											[
												99 => 'JM / JAM',
											],
									],
								6 =>
									[
										9 =>
											[
												99 => 'KN / KNA',
											],
										8 =>
											[
												99 => 'TT / TTO',
											],
									],
							],
						9 =>
							[
								3 =>
									[
										9 =>
											[
												99 => 'PR / PRI',
											],
									],
							],
					],
			],
		7 =>
			[
				99 => 'RU / RUS',
			],
		2 =>
			[
				0 =>
					[
						99 => 'EG / EGY',
					],
				7 =>
					[
						99 => 'ZA / ZAF',
					],
				1 =>
					[
						1 =>
							[
								99 => 'SS / SSD',
							],
						2 =>
							[
								99 => 'EH / ESH',
							],
						3 =>
							[
								99 => 'DZ / DZA',
							],
						6 =>
							[
								99 => 'TN / TUN',
							],
						8 =>
							[
								99 => 'LY / LBY',
							],
					],
				2 =>
					[
						0 =>
							[
								99 => 'GM / GMB',
							],
						1 =>
							[
								99 => 'SN / SEN',
							],
						2 =>
							[
								99 => 'MR / MRT',
							],
						3 =>
							[
								99 => 'ML / MLI',
							],
						4 =>
							[
								99 => 'GN / GIN',
							],
						5 =>
							[
								99 => 'CI / CIV',
							],
						6 =>
							[
								99 => 'BF / BFA',
							],
						7 =>
							[
								99 => 'NE / NER',
							],
						8 =>
							[
								99 => 'TG / TGO',
							],
						9 =>
							[
								99 => 'BJ / BEN',
							],
					],
				3 =>
					[
						0 =>
							[
								99 => 'MU / MUS',
							],
						1 =>
							[
								99 => 'LR / LBR',
							],
						2 =>
							[
								99 => 'SL / SLE',
							],
						3 =>
							[
								99 => 'GH / GHA',
							],
						4 =>
							[
								99 => 'NG / NGA',
							],
						5 =>
							[
								99 => 'TD / TCD',
							],
						6 =>
							[
								99 => 'CF / CAF',
							],
						7 =>
							[
								99 => 'CM / CMR',
							],
						8 =>
							[
								99 => 'CV / CPV',
							],
						9 =>
							[
								99 => 'ST / STP',
							],
					],
				4 =>
					[
						0 =>
							[
								99 => 'GQ / GNQ',
							],
						1 =>
							[
								99 => 'GA / GAB',
							],
						2 =>
							[
								99 => 'CG / COG',
							],
						3 =>
							[
								99 => 'CD / COD',
							],
						4 =>
							[
								99 => 'AO / AGO',
							],
						5 =>
							[
								99 => 'GW / GNB',
							],
						6 =>
							[
								99 => 'IO / IOT',
							],
						8 =>
							[
								99 => 'SC / SYC',
							],
						9 =>
							[
								99 => 'SD / SDN',
							],
					],
				5 =>
					[
						0 =>
							[
								99 => 'RW / RWA',
							],
						1 =>
							[
								99 => 'ET / ETH',
							],
						2 =>
							[
								99 => 'SO / SOM',
							],
						3 =>
							[
								99 => 'DJ / DJI',
							],
						4 =>
							[
								99 => 'KE / KEN',
							],
						5 =>
							[
								99 => 'TZ / TZA',
							],
						6 =>
							[
								99 => 'UG / UGA',
							],
						7 =>
							[
								99 => 'BI / BDI',
							],
						8 =>
							[
								99 => 'MZ / MOZ',
							],
					],
				6 =>
					[
						0 =>
							[
								99 => 'ZM / ZMB',
							],
						1 =>
							[
								99 => 'MG / MDG',
							],
						2 =>
							[
								99 => 'RE / REU',
							],
						3 =>
							[
								99 => 'ZW / ZWE',
							],
						4 =>
							[
								99 => 'NA / NAM',
							],
						5 =>
							[
								99 => 'MW / MWI',
							],
						6 =>
							[
								99 => 'LS / LSO',
							],
						7 =>
							[
								99 => 'BW / BWA',
							],
						8 =>
							[
								99 => 'SZ / SWZ',
							],
						9 =>
							[
								99 => 'KM / COM',
							],
					],
				9 =>
					[
						0 =>
							[
								99 => 'SH / SHN',
							],
						1 =>
							[
								99 => 'ER / ERI',
							],
						7 =>
							[
								99 => 'AW / ABW',
							],
						8 =>
							[
								99 => 'FO / FRO',
							],
						9 =>
							[
								99 => 'GL / GRL',
							],
					],
			],
		3 =>
			[
				0 =>
					[
						99 => 'GR / GRC',
					],
				1 =>
					[
						99 => 'NL / NLD',
					],
				2 =>
					[
						99 => 'BE / BEL',
					],
				3 =>
					[
						99 => 'FR / FRA',
					],
				4 =>
					[
						99 => 'ES / ESP',
					],
				6 =>
					[
						99 => 'HU / HUN',
					],
				9 =>
					[
						99 => 'IT / ITA',
					],
				5 =>
					[
						0 =>
							[
								99 => 'GI / GIB',
							],
						1 =>
							[
								99 => 'PT / PRT',
							],
						2 =>
							[
								99 => 'LU / LUX',
							],
						3 =>
							[
								99 => 'IE / IRL',
							],
						4 =>
							[
								99 => 'IS / ISL',
							],
						5 =>
							[
								99 => 'AL / ALB',
							],
						6 =>
							[
								99 => 'MT / MLT',
							],
						7 =>
							[
								99 => 'CY / CYP',
							],
						8 =>
							[
								99 => 'FI / FIN',
							],
						9 =>
							[
								99 => 'BG / BGR',
							],
					],
				7 =>
					[
						0 =>
							[
								99 => 'LT / LTU',
							],
						1 =>
							[
								99 => 'LV / LVA',
							],
						2 =>
							[
								99 => 'EE / EST',
							],
						3 =>
							[
								99 => 'MD / MDA',
							],
						4 =>
							[
								99 => 'AM / ARM',
							],
						5 =>
							[
								99 => 'BY / BLR',
							],
						6 =>
							[
								99 => 'AD / AND',
							],
						7 =>
							[
								99 => 'MC / MCO',
							],
						8 =>
							[
								99 => 'SM / SMR',
							],
						9 =>
							[
								99 => 'VA / VAT',
							],
					],
				8 =>
					[
						0 =>
							[
								99 => 'UA / UKR',
							],
						1 =>
							[
								99 => 'RS / SRB',
							],
						2 =>
							[
								99 => 'ME / MNE',
							],
						3 =>
							[
								99 => 'XK / XKX',
							],
						5 =>
							[
								99 => 'HR / HRV',
							],
						6 =>
							[
								99 => 'SI / SVN',
							],
						7 =>
							[
								99 => 'BA / BIH',
							],
						9 =>
							[
								99 => 'MK / MKD',
							],
					],
			],
		4 =>
			[
				0 =>
					[
						99 => 'RO / ROU',
					],
				1 =>
					[
						99 => 'CH / CHE',
					],
				3 =>
					[
						99 => 'AT / AUT',
					],
				4 =>
					[
						99  => 'GB / GBR',
						'-' =>
							[
								1 =>
									[
										4 =>
											[
												8 =>
													[
														1 =>
															[
																99 => 'GG / GGY',
															],
													],
											],
										6 =>
											[
												2 =>
													[
														4 =>
															[
																99 => 'IM / IMN',
															],
													],
											],
										5 =>
											[
												3 =>
													[
														4 =>
															[
																99 => 'JE / JEY',
															],
													],
											],
									],
							],
					],
				5 =>
					[
						99 => 'DK / DNK',
					],
				6 =>
					[
						99 => 'SE / SWE',
					],
				7 =>
					[
						99 => 'SJ / SJM',
					],
				8 =>
					[
						99 => 'PL / POL',
					],
				9 =>
					[
						99 => 'DE / DEU',
					],
				2 =>
					[
						0 =>
							[
								99 => 'CZ / CZE',
							],
						1 =>
							[
								99 => 'SK / SVK',
							],
						3 =>
							[
								99 => 'LI / LIE',
							],
					],
			],
		5 =>
			[
				1 =>
					[
						99 => 'PE / PER',
					],
				2 =>
					[
						99 => 'MX / MEX',
					],
				3 =>
					[
						99 => 'CU / CUB',
					],
				4 =>
					[
						99 => 'AR / ARG',
					],
				5 =>
					[
						99 => 'BR / BRA',
					],
				6 =>
					[
						99 => 'CL / CHL',
					],
				7 =>
					[
						99 => 'CO / COL',
					],
				8 =>
					[
						99 => 'VE / VEN',
					],
				0 =>
					[
						0 =>
							[
								99 => 'FK / FLK',
							],
						1 =>
							[
								99 => 'BZ / BLZ',
							],
						2 =>
							[
								99 => 'GT / GTM',
							],
						3 =>
							[
								99 => 'SV / SLV',
							],
						4 =>
							[
								99 => 'HN / HND',
							],
						5 =>
							[
								99 => 'NI / NIC',
							],
						6 =>
							[
								99 => 'CR / CRI',
							],
						7 =>
							[
								99 => 'PA / PAN',
							],
						8 =>
							[
								99 => 'PM / SPM',
							],
						9 =>
							[
								99 => 'HT / HTI',
							],
					],
				9 =>
					[
						0 =>
							[
								99 => 'MF / MAF',
							],
						1 =>
							[
								99 => 'BO / BOL',
							],
						2 =>
							[
								99 => 'GY / GUY',
							],
						3 =>
							[
								99 => 'EC / ECU',
							],
						5 =>
							[
								99 => 'PY / PRY',
							],
						7 =>
							[
								99 => 'SR / SUR',
							],
						8 =>
							[
								99 => 'UY / URY',
							],
						9 =>
							[
								99 => 'AN / ANT',
							],
					],
			],
		6 =>
			[
				0 =>
					[
						99 => 'MY / MYS',
					],
				1 =>
					[
						99 => 'CC / CCK',
					],
				2 =>
					[
						99 => 'ID / IDN',
					],
				3 =>
					[
						99 => 'PH / PHL',
					],
				4 =>
					[
						99 => 'PN / PCN',
					],
				5 =>
					[
						99 => 'SG / SGP',
					],
				6 =>
					[
						99 => 'TH / THA',
					],
				7 =>
					[
						0 =>
							[
								99 => 'TL / TLS',
							],
						2 =>
							[
								99 => 'AQ / ATA',
							],
						3 =>
							[
								99 => 'BN / BRN',
							],
						4 =>
							[
								99 => 'NR / NRU',
							],
						5 =>
							[
								99 => 'PG / PNG',
							],
						6 =>
							[
								99 => 'TO / TON',
							],
						7 =>
							[
								99 => 'SB / SLB',
							],
						8 =>
							[
								99 => 'VU / VUT',
							],
						9 =>
							[
								99 => 'FJ / FJI',
							],
					],
				8 =>
					[
						0 =>
							[
								99 => 'PW / PLW',
							],
						1 =>
							[
								99 => 'WF / WLF',
							],
						2 =>
							[
								99 => 'CK / COK',
							],
						3 =>
							[
								99 => 'NU / NIU',
							],
						5 =>
							[
								99 => 'WS / WSM',
							],
						6 =>
							[
								99 => 'KI / KIR',
							],
						7 =>
							[
								99 => 'NC / NCL',
							],
						8 =>
							[
								99 => 'TV / TUV',
							],
						9 =>
							[
								99 => 'PF / PYF',
							],
					],
				9 =>
					[
						0 =>
							[
								99 => 'TK / TKL',
							],
						1 =>
							[
								99 => 'FM / FSM',
							],
						2 =>
							[
								99 => 'MH / MHL',
							],
					],
			],
		8 =>
			[
				1 =>
					[
						99 => 'JP / JPN',
					],
				2 =>
					[
						99 => 'KR / KOR',
					],
				4 =>
					[
						99 => 'VN / VNM',
					],
				6 =>
					[
						99 => 'CN / CHN',
					],
				5 =>
					[
						0 =>
							[
								99 => 'KP / PRK',
							],
						2 =>
							[
								99 => 'HK / HKG',
							],
						3 =>
							[
								99 => 'MO / MAC',
							],
						5 =>
							[
								99 => 'KH / KHM',
							],
						6 =>
							[
								99 => 'LA / LAO',
							],
					],
				8 =>
					[
						0 =>
							[
								99 => 'BD / BGD',
							],
						6 =>
							[
								99 => 'TW / TWN',
							],
					],
			],
		9 =>
			[
				0 =>
					[
						99 => 'TR / TUR',
					],
				1 =>
					[
						99 => 'IN / IND',
					],
				2 =>
					[
						99 => 'PK / PAK',
					],
				3 =>
					[
						99 => 'AF / AFG',
					],
				4 =>
					[
						99 => 'LK / LKA',
					],
				5 =>
					[
						99 => 'MM / MMR',
					],
				8 =>
					[
						99 => 'IR / IRN',
					],
				6 =>
					[
						0 =>
							[
								99 => 'MV / MDV',
							],
						1 =>
							[
								99 => 'LB / LBN',
							],
						2 =>
							[
								99 => 'JO / JOR',
							],
						3 =>
							[
								99 => 'SY / SYR',
							],
						4 =>
							[
								99 => 'IQ / IRQ',
							],
						5 =>
							[
								99 => 'KW / KWT',
							],
						6 =>
							[
								99 => 'SA / SAU',
							],
						7 =>
							[
								99 => 'YE / YEM',
							],
						8 =>
							[
								99 => 'OM / OMN',
							],
					],
				7 =>
					[
						0 =>
							[
								99 => 'PS / PSE',
							],
						1 =>
							[
								99 => 'AE / ARE',
							],
						2 =>
							[
								99 => 'IL / ISR',
							],
						3 =>
							[
								99 => 'BH / BHR',
							],
						4 =>
							[
								99 => 'QA / QAT',
							],
						5 =>
							[
								99 => 'BT / BTN',
							],
						6 =>
							[
								99 => 'MN / MNG',
							],
						7 =>
							[
								99 => 'NP / NPL',
							],
					],
				9 =>
					[
						2 =>
							[
								99 => 'TJ / TJK',
							],
						3 =>
							[
								99 => 'TM / TKM',
							],
						4 =>
							[
								99 => 'AZ / AZE',
							],
						5 =>
							[
								99 => 'GE / GEO',
							],
						6 =>
							[
								99 => 'KG / KGZ',
							],
						8 =>
							[
								99 => 'UZ / UZB',
							],
					],
			],
	];

	public string $prefix {
		get {
			if (isset($this->prefix)) {
				return $this->prefix;
			}
			$this->findPrefix();
			return $this->prefix;
		}
	}

	public string $prefixCountry {
		get {
			if (empty($this->prefixCountry)) {
				$this->findPrefix();
			}
			return $this->prefixCountry;
		}
	}
	public string $number {
		get {
			if (isset($this->number)) {
				return $this->number;
			}

			$phone = preg_replace('/\s/', '', $this->phone);
			if (str_starts_with($this->phone, '+')) {
				$phone = substr($phone, 1);
			}
			elseif (str_starts_with($this->phone, '00')) {
				$phone = substr($phone, 2);
			}

			$prefixLen = strlen($this->prefix);
			if ($prefixLen > 0) {
				$this->number = substr($phone, $prefixLen);
			} else {
				$this->number = $phone;
			}
			return $this->number;
		}
	}

	public function __construct(
		private readonly string $phone,
	) {
	}

	public function __toString() {
		$prefix = $this->prefix;
		if ($prefix !== '') {
			return '+'.$prefix.$this->number;
		}
		return $this->number;
	}

	private function findPrefix() : void {
		$phone = preg_replace('/\s/', '', $this->phone);
		if (str_starts_with($this->phone, '+')) {
			$phone = substr($phone, 1);
		}
		elseif (str_starts_with($this->phone, '00')) {
			$phone = substr($phone, 2);
		}
		else {
			$this->prefix = ''; // No prefix
			$this->prefixCountry = '';
			return;
		}

		$numbers = str_split($phone);
		$map = self::PREFIXES;
		$this->prefixCountry = '';
		$this->prefix = '';
		foreach ($numbers as $number) {
			if (!isset($map[$number])) {
				$this->prefixCountry = $map[99] ?? '';
				break;
			}
			$map = $map[$number];
			$this->prefix .= $number;
		}
	}
}