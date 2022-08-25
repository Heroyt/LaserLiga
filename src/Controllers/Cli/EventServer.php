<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Cli;

use App\Services\EventService;
use Lsr\Core\CliController;
use Socket;

class EventServer extends CliController
{

	public const RESTART_TIME = 3600 * 10;

	/** @var Socket[] */
	private array $clients = [];

	/**
	 * Start a WS server
	 *
	 * Allows for multiple WS connections. Pools data once every second. Broadcasts any incoming messages and new events from DB to all connected clients.
	 *
	 * @return never
	 */
	public function start() : never {
		$start = microtime(true);
		$this->echo('Starting server', 'info');
		$null = null;
		// Create the master (=server) socket
		// Using IPv4 and TCP
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($sock === false) {
			$this->errorPrint('Cannot open socket.');
			exit;
		}
		socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
		// Listen for connection on predefined port
		socket_bind($sock, '0.0.0.0', EVENT_PORT);
		socket_listen($sock);
		// Set blocking for socket_select() function to detect new connections
		socket_set_block($sock);

		// Try to register self auto-restart on error or kill.
		// The only way to stop the process should be SIGINT interrupt (CTRL+C) and system shutdown.
		// This function also handles gracefully closing opened sockets.
		// Stolen from: https://stackoverflow.com/a/11463187
		$handleInterrupt = function(?int $sig = null) use ($sock) {
			global $_, $argv;
			if ($sig !== null) {
				$this->errorPrint('Received signal '.$sig);
			}
			// Close sockets
			$this->errorPrint('Closing sockets');
			socket_shutdown($sock);
			socket_close($sock);
			foreach ($this->clients as $s) {
				if ($s === $sock) {
					continue;
				}
				socket_close($s);
			}
			// Restart
			if (($sig !== SIGINT) && pcntl_exec($_, $argv) === false) {
				$this->errorPrint('Failed to restart process');
			}
			exit;
		};
		register_shutdown_function($handleInterrupt);
		pcntl_signal(SIGTERM, $handleInterrupt); // kill
		pcntl_signal(SIGHUP, $handleInterrupt);  // kill -s HUP or kill -1
		pcntl_signal(SIGINT, $handleInterrupt);  // CTRL + C

		do {
			// Check time to auto-restart after 10 hours
			if (microtime(true) - $start > $this::RESTART_TIME) {
				$this->echo('Restarting...');
				exit();
			}

			// The $newSockets list will be filtered by the socket_select() to only contain those sockets which have some data to read
			$newSockets = $this->clients;
			$newSockets[] = $sock;

			// The socket select function will check for incoming messages and connections
			// It will also serve as an interval timer for DB polling
			@socket_select($newSockets, $null, $null, 1);

			// If the main socket received a new connect message -> open a new client socket
			if (in_array($sock, $newSockets, true)) {
				$client = socket_accept($sock);
				if ($client === false) {
					$this->errorPrint('Socker_accept failed');
					continue;
				}

				// Debug message
				socket_getpeername($client, $clientIP);
				$this->echo('Client connected.', $clientIP);

				// Send WebSocket handshake headers.
				$request = @socket_read($client, 10000);
				if ($request === false) {
					// Initial request failed
					$this->echo("socket_read() failed; reason: ".socket_strerror(socket_last_error($client)), 'error');
					socket_close($client);
				}
				else {
					// Get the key
					preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
					$key = base64_encode(pack(
																 'H*',
																 sha1(($matches[1] ?? '').'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
															 ));
					// WS HTTP headers (WS ACK)
					$headers = "HTTP/1.1 101 Switching Protocols\r\n";
					$headers .= "Upgrade: websocket\r\n";
					$headers .= "Connection: Upgrade\r\n";
					$headers .= "Sec-WebSocket-Version: 13\r\n";
					$headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
					socket_write($client, $headers, strlen($headers));
					// Add to client-pool
					$this->clients[] = $client;
				}
			}

			// Read from all sockets that sent a message
			foreach ($newSockets as $readClient) {
				if ($readClient === $sock) {
					continue;
				}
				$this->clientRead($readClient);
			}

			// Db polling
			$this->sentUnsentMessages();
		} while (true); // Infinite server loop
	}

	/**
	 * Formatted echo
	 *
	 * @param string $message
	 * @param string $clientIP
	 *
	 * @return void
	 */
	public function echo(string $message, string $clientIP = '') : void {
		echo date('[Y-m-d H:i:s]').' '.$clientIP.' '.trim($message).PHP_EOL;
	}

	/**
	 * Receive a message from client
	 *
	 * @param Socket $client
	 *
	 * @return void
	 */
	private function clientRead(Socket $client) : void {
		socket_getpeername($client, $clientIP);

		// Receive any message from client
		if (socket_recv($client, $socketData, 1024, 0) >= 1) {
			$message = $this->unseal($socketData);
			// Prevent wrong encoding error
			// This can happen if the disconnect message is incorrectly read
			$validUTF8 = mb_check_encoding($message, 'UTF-8');
			if ($validUTF8) {
				$this->echo($message, $clientIP);
				$this->broadcast($message);
			}
		}

		// Check for disconnections
		$test = [$client];
		$null = null;
		// Test the socket to prevent socket_read() from blocking the process
		@socket_select($test, $null, $null, 0, 10);
		$socketData = @socket_read($client, 1024, PHP_NORMAL_READ);
		// Error means the client is disconnected
		if ($socketData === false) {
			$this->echo('Client disconnected.', $clientIP);
			// Remove the client socket from the client-pool
			$key = array_search($client, $this->clients, true);
			if (isset($this->clients[$key])) {
				unset($this->clients[$key]);
			}
			// Close the socket
			socket_close($client);
		}
	}

	/**
	 * Parse the WS data
	 *
	 * @param string $socketData
	 *
	 * @return string
	 */
	public function unseal(string $socketData) : string {
		$length = ord($socketData[1]) & 127;
		if ($length === 126) {
			$masks = substr($socketData, 4, 4);
			$data = substr($socketData, 8);
		}
		elseif ($length === 127) {
			$masks = substr($socketData, 10, 4);
			$data = substr($socketData, 14);
		}
		else {
			$masks = substr($socketData, 2, 4);
			$data = substr($socketData, 6);
		}
		$socketData = "";
		$len = strlen($data);
		for ($i = 0; $i < $len; ++$i) {
			$socketData .= $data[$i] ^ $masks[$i % 4];
		}
		return $socketData;
	}

	/**
	 * Send a message to all listening clients
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function broadcast(string $message) : void {
		foreach ($this->clients as $key => $client) {
			// Send a message to socket
			$this->sendTo($client, $message);
		}
	}

	/**
	 * @param Socket $client
	 * @param string $message
	 *
	 * @return bool
	 */
	public function sendTo(Socket $client, string $message) : bool {
		return socket_write($client, chr(129).chr(strlen($message)).$message) > 0;
	}

	/**
	 * Broadcasts all new events from the database
	 *
	 * @return void
	 */
	private function sentUnsentMessages() : void {
		$events = EventService::getUnsent();
		$ids = [];
		foreach ($events as $event) {
			$this->echo($event->message, 'event');
			$this->broadcast($event->message);
			$ids[] = $event->id_event;
		}
		if (!empty($ids) && !EventService::updateSent($ids)) {
			$this->echo('Failed to flag events as sent', 'error');
		}
	}

	/**
	 * Add a WS header
	 *
	 * @param string $socketData
	 *
	 * @return string
	 */
	public function seal(string $socketData) : string {
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($socketData);

		if ($length <= 125) {
			$header = pack('CC', $b1, $length);
		}
		elseif ($length < 65536) {
			$header = pack('CCn', $b1, 126, $length);
		}
		else {
			$header = pack('CCNN', $b1, 127, $length);
		}
		return $header.$socketData;
	}

}