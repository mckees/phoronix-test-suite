<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2015 - 2016, Phoronix Media
	Copyright (C) 2015 - 2016, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_stress_run_manager extends pts_test_run_manager
{
	private $multi_test_stress_start_time;
	private $stress_tests_executed;

	public function multi_test_stress_run_execute($tests_to_run_concurrently = 3, $total_loop_time = false)
	{
		$continue_test_flag = true;
		pts_client::$display->test_run_process_start($this);
		$this->disable_dynamic_run_count();
		$this->multi_test_stress_run = $tests_to_run_concurrently;
		$possible_tests_to_run = $this->get_tests_to_run();
		$tests_pids_active = array();
		$loop_until_time = is_numeric($total_loop_time) && $total_loop_time > 1 ? time() + $total_loop_time : false;
		$time_report_counter = time();
		$this->stress_tests_executed = array();
		$this->multi_test_stress_start_time = time();

		$thread_collection_dir = pts_client::create_temporary_directory('stress-threads');

		// SENSOR SETUP WORK
		$sensors_to_monitor = array();
		$sensor_interval_frequency = is_numeric($total_loop_time) && $total_loop_time > 1 ? max($total_loop_time / 1000, 3) : 6;
		$sensor_data_archived = array();
		$sensor_data_archived_units = array();
		$sensor_time_since_last_poll = time();
		foreach(phodevi::supported_sensors(array('cpu_temp', 'cpu_usage', 'gpu_usage', 'gpu_temp', 'hdd_read_speed', 'hdd_write_speed', 'memory_usage', 'swap_usage', 'sys_temp')) as $sensor)
		{
			$supported_devices = call_user_func(array($sensor[2], 'get_supported_devices'));

			if($supported_devices === NULL)
			{
				$supported_devices = array(null);
			}

			foreach($supported_devices as $device)
			{
				$sensor_object = new $sensor[2](0, $device);
				if(phodevi::read_sensor($sensor_object) != -1)
				{
					array_push($sensors_to_monitor, $sensor_object);
					$sensor_data_archived[phodevi::sensor_object_name($sensor_object)] = array();
					$sensor_data_archived_units[phodevi::sensor_object_name($sensor_object)] = phodevi::read_sensor_object_unit($sensor_object);
				}
			}
		}

		while(!empty($possible_tests_to_run))
		{
			if($continue_test_flag == false)
				break;

			if(($time_report_counter + 30) <= time() && count(pts_file_io::glob($thread_collection_dir . '*')) > 0)
			{
				$report_buffer = PHP_EOL . '###### STRESS RUN CURRENT STATUS ' . date('H:i M j') . ' ####' . PHP_EOL;
				$report_buffer .= 'ELAPSED TIME: ' . pts_strings::format_time(time() - $this->multi_test_stress_start_time) . PHP_EOL;
				if($loop_until_time > time())
				{
					$report_buffer .= 'TIME REMAINING: ' . pts_strings::format_time($loop_until_time - time()) . PHP_EOL;
				}
				else if($total_loop_time == 'infinite')
				{
					$report_buffer .= 'INFINITE TESTING; TESTING UNTIL INTERRUPTED' . PHP_EOL;
				}
				else
				{
					$report_buffer .= 'WAITING FOR CURRENT TEST RUN QUEUE TO FINISH.' . PHP_EOL;
				}
				$report_buffer .= 'NUMBER OF CONCURRENT TESTS PERMITTED: ' . $tests_to_run_concurrently . PHP_EOL;
				$report_buffer .= 'TESTS CURRENTLY ACTIVE: ' . PHP_EOL;

				$table = array();
				foreach(pts_file_io::glob($thread_collection_dir . '*') as $pid_file)
				{
					$test = pts_file_io::file_get_contents($pid_file);
					$table[] = array($test, '[PID: ' . basename($pid_file) . ']');
				}
				$report_buffer .= pts_user_io::display_text_table($table, '   - ', 2) . PHP_EOL;


				$report_buffer .= 'TEST SUBSYSTEMS ACTIVE: ' . PHP_EOL;
				foreach($test_types_active as &$type)
				{
					$report_buffer .= '   - ' . $type . PHP_EOL;
				}

				$report_buffer .= 'CURRENT SYSTEM SENSORS: ' . PHP_EOL;
				$table = array();
				foreach($sensors_to_monitor as &$sensor_object)
				{
					// Hacky way to avoid reporting individual CPU core usages each time, save it for summaries
					if(strpos(phodevi::sensor_object_name($sensor_object), 'CPU Usage (CPU') !== false)
						continue;

					$sensor_val = phodevi::read_sensor($sensor_object);

					if($sensor_val > 0)
					{
						$table[] = array(phodevi::sensor_object_name($sensor_object) . ': ', pts_math::set_precision($sensor_val, 2), phodevi::read_sensor_object_unit($sensor_object));
					}
				}
				$report_buffer .= pts_user_io::display_text_table($table, '   - ', 2) . PHP_EOL;
				$report_buffer .= '######' . PHP_EOL;
				echo $report_buffer;
				$time_report_counter = time();
			}
			$test_types_active = array();
			$test_identifiers_active = array();
			foreach(pts_file_io::glob($thread_collection_dir . '*') as $pid_file)
			{
				$pid = basename($pid_file);

				if(!file_exists('/proc/' . $pid))
				{
					unlink($pid_file);
					continue;
				}

				$test = new pts_test_profile(file_get_contents($pid_file));

				if(!in_array($test->get_test_hardware_type(), $test_types_active))
				{
					$test_types_active[] = $test->get_test_hardware_type();
				}
				if(!in_array($test->get_identifier(), $test_identifiers_active))
				{
					$test_identifiers_active[] = $test->get_identifier();
				}

			}

			if(!empty($possible_tests_to_run) && count(pts_file_io::glob($thread_collection_dir . '*')) < $tests_to_run_concurrently && (!$total_loop_time || $total_loop_time == 'infinite' || $loop_until_time > time()))
			{
				shuffle($possible_tests_to_run);

				$test_to_run = false;
				$test_run_index = -1;

				if(getenv('DONT_BALANCE_TESTS_FOR_SUBSYSTEMS') == false)
				{
					// Try to pick a test for a hardware subsystem not yet being explicitly utilized
					foreach($possible_tests_to_run as $i => $test)
					{
						if(!in_array($test->test_profile->get_test_hardware_type(), $test_types_active))
						{
							$test_run_index = $i;
							$test_to_run = $test;
							break;
						}
					}
				}

				if($test_run_index == -1 && getenv('DONT_TRY_TO_ENSURE_TESTS_ARE_UNIQUE') == false)
				{
					// Try to pick a test from a test profile not currently active
					foreach($possible_tests_to_run as $i => $test)
					{
						if(!in_array($test->test_profile->get_identifier(), $test_identifiers_active))
						{
							$test_run_index = $i;
							$test_to_run = $test;
							break;
						}
					}
				}

				if($test_run_index == -1)
				{
					// Last resort, just randomly pick a true "random" test
					$test_run_index = array_rand(array_keys($possible_tests_to_run));
					$test_to_run = $possible_tests_to_run[$test_run_index];
				}

				$pid = pcntl_fork();
				if($pid == -1)
				{
					echo 'Forking Failure.';
				}
				if($pid)
				{
					// parent
					$test_identifier = $test_to_run->test_profile->get_identifier();
					file_put_contents($thread_collection_dir . $pid, $test_identifier);

					if(!isset($this->stress_tests_executed[$test_identifier]))
					{
						$this->stress_tests_executed[$test_identifier] = 1;
					}
					else
					{
						$this->stress_tests_executed[$test_identifier]++;
					}
				}
				else
				{
					// child
					$continue_test_flag = $this->process_test_run_request($test_to_run);
					pts_file_io::unlink($thread_collection_dir . getmypid());
					echo PHP_EOL;
					exit;
				}
				if($total_loop_time == false)
				{
					unset($possible_tests_to_run[$test_run_index]);
				}
				else if($total_loop_time == 'infinite')
				{
						echo 'Continuing to test indefinitely' . PHP_EOL;
				}
				else
				{
					if($loop_until_time > time())
					{
						$time_left = ceil(($loop_until_time - time()) / 60);
					//	echo 'Continuing to test for ' . $time_left . ' more minutes' . PHP_EOL;
					}
				}
			}

			if(is_numeric($loop_until_time) && $loop_until_time < time())
			{
				// Time to Quit

				// This halt-testing touch will let tests exit early (i.e. between multiple run steps)
				file_put_contents(PTS_USER_PATH . 'halt-testing', 'stress-run is done... This text really is not important, just checking for file presence.');
				echo 'TEST TIME EXPIRED; NO NEW TESTS WILL EXECUTE; CURRENT TESTS WILL FINISH' . PHP_EOL . PHP_EOL;

				$report_buffer = PHP_EOL . '###### FINAL REPORT ####' . PHP_EOL;
				$report_buffer .= 'ELAPSED TIME: ' . pts_strings::format_time(time() - $this->multi_test_stress_start_time) . PHP_EOL . PHP_EOL;

				$table = array();
				if(!empty($this->stress_tests_executed))
				{
					$report_buffer .= 'TESTS EXECUTED: ' . PHP_EOL;
					ksort($this->stress_tests_executed);

					foreach($this->stress_tests_executed as $test => $times)
					{
						$table[] = array($test . ': ', $times);
					}
				}
				$report_buffer .= pts_user_io::display_text_table($table, '   - ', 2) . PHP_EOL;

				$table = array(array('SENSOR', 'MIN', 'AVG', 'MAX'));
				foreach($sensor_data_archived as $sensor_name => &$sensor_data)
				{
					$max_val = max($sensor_data);

					if($max_val > 0)
					{
						$table[] = array($sensor_name . ': ', pts_math::set_precision(min($sensor_data), 2), pts_math::set_precision(array_sum($sensor_data) / count($sensor_data), 2), pts_math::set_precision($max_val, 2), $sensor_data_archived_units[$sensor_name]);
					}
				}
				$report_buffer .= pts_user_io::display_text_table($table, '   - ', 2) . PHP_EOL;
				$report_buffer .= '######' . PHP_EOL;
				echo $report_buffer;
				break;
			}

			if($sensor_time_since_last_poll + $sensor_interval_frequency < time())
			{
				foreach($sensors_to_monitor as &$sensor_object)
				{
					$sensor_data_archived[phodevi::sensor_object_name($sensor_object)][] = phodevi::read_sensor($sensor_object);
				}
				$sensor_time_since_last_poll = time();
			}
			else
			{
				sleep(1);
			}
		}

		pts_file_io::delete($thread_collection_dir, null, true);

		foreach($this->get_tests_to_run() as $run_request)
		{
			// Remove cache shares
			foreach(pts_file_io::glob($run_request->test_profile->get_install_dir() . 'cache-share-*.pt2so') as $cache_share_file)
			{
				unlink($cache_share_file);
			}
		}

		return true;
	}
}

?>
