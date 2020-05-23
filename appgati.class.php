<?php

use Exception;

/**
 * @file
 *  A class to help in gauging page load time of your PHP applications.
 *  It does nothing different than using built-in PHP functions other than
 *  providing cleaner implementation and handling some small
 *  calculations for you :)
 *  It does not work on Windows!
 */

/**
 * Class code.
 */
class AppGati
{
    private $format;

    /**
     * Constructor.
     *
     * @param string $format
     */
    public function __construct($format = 'array')
    {
        $this->format = $format;
        //parent::__construct();
    }

    /**
     * set the format of the result to be returned
     *
     * @param string $format format string array,string or json
     */
    public function setFormat($format = 'array'): void
    {
        $this->format = $format;
    }

    /**
     * Set a step for benchmarking.
     *
     * @param null $label
     */
    public function Step($label = null): void
    {
        $this->SetTime($label);
        $this->SetUsage($label);
        $this->SetMemory($label);
        $this->SetPeakMemory($label);
    }

    /**
     * Set time by label.
     *
     * @param null $label
     */
    protected function SetTime($label = null): void
    {
        $label = $label ? $label . '_time' : 'SetTime';
        $this->$label = $this->Time();
    }

    /**
     * Return time.
     */
    public function Time()
    {
        return microtime(true);
    }

    /**
     * Set usage by label.
     *
     * @param null $label
     */
    protected function SetUsage($label = null): void
    {
        $label = $label ? $label . '_usage' : 'SetUsage';
        $this->$label = $this->Usage();
    }

    /**
     * Return usage.
     *
     * @return array|false|string|string[] Result of calling getrusage() in form of an array or a string.
     *  Result of calling getrusage() in form of an array or a string.
     */
    public function Usage()
    {
        // Return array by default.
        $data = '';
        switch ($this->format) {
            case 'array':
                $data = getrusage();
                break;
            case 'string':
                $data = str_replace('&', ', ', http_build_query(getrusage()));
                break;
            case 'json':
                $data = json_encode(getrusage());
                break;
        }

        return $data;
    }

    /**
     * Set memory by label.
     *
     * @param null $label
     */
    protected function SetMemory($label = null): void
    {
        $label = $label ? $label . '_memory' : 'SetMemory';
        $this->$label = $this->Memory();
    }

    /**
     * Return memory usage.
     */
    public function Memory(): int
    {
        // Not using true so to get actual memory usage.
        return memory_get_usage();
    }

    /**
     * Get peak memory by label.
     *
     * @param null $label
     */
    protected function SetPeakMemory($label = null): void
    {
        $label = $label ? $label . '_peak_memory' : 'GetPeakMemory';
        $this->$label = memory_get_peak_usage();
    }

    /**
     * Get stats.
     *
     * @param
     *  $plabel: Primary label. Should be set prior to secondary label.
     * @param
     *  $slabel: Secondary label. Should be set after primary label.
     *
     * @return array|Exception
     */
    public function Report($plabel, $slabel)
    {
        try {
            $array = array();
            // Get server load in last minute.
            $load = $this->ServerLoad();
            // Get results.
            $results = $this->CheckGati($plabel, $slabel);
            // Prepare array.
            $array['Clock time in seconds'] = $results['time'];
            $array['Time taken in User Mode in seconds'] = $results['usage']['ru_utime.tv'] ?? 'Not Available';
            $array['Time taken in System Mode in seconds'] = $results['usage']['ru_stime.tv'] ?? 'Not Available';
            $array['Total time taken in Kernel in seconds'] =
                $results['usage']['ru_stime.tv'] + $results['usage']['ru_utime.tv'];
            $array['Memory limit in MB'] = str_replace('M', '', ini_get('memory_limit'));
            $array['Memory usage in MB'] = $results['memory'] ?? 'Not Available';
            $array['Peak memory usage in MB'] = $results['peak_memory'] ?? 'Not Available';
            $array['Average server load in last minute'] = $load['0'];
            $array['Maximum resident shared size in KB'] = $results['usage']['ru_maxrss'] ?? 'Not Available';
            $array['Integral shared memory size'] = $results['usage']['ru_ixrss'] ?? 'Not Available';
            $array['Integral unshared data size'] = $results['usage']['ru_idrss'] ?? 'Not Available';
            $array['Integral unshared stack size'] = $results['usage']['ru_isrss'] ?? 'Not Available';
            $array['Number of page reclaims'] = $results['usage']['ru_minflt'] ?? 'Not Available';
            $array['Number of page faults'] = $results['usage']['ru_majflt'] ?? 'Not Available';
            $array['Number of block input operations'] = $results['usage']['ru_inblock'] ?? 'Not Available';
            $array['Number of block output operations'] = $results['usage']['ru_outblock'] ?? 'Not Available';
            $array['Number of messages sent'] = $results['usage']['ru_msgsnd'] ?? 'Not Available';
            $array['Number of messages received'] = $results['usage']['ru_msgrcv'] ?? 'Not Available';
            $array['Number of signals received'] = $results['usage']['ru_nsignals'] ?? 'Not Available';
            $array['Number of voluntary context switches'] = $results['usage']['ru_nvcsw'] ?? 'Not Available';
            $array['Number of involuntary context switches'] = $results['usage']['ru_nivcsw'] ?? 'Not Available';

            return $array;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Return average server load.
     */
    public function ServerLoad(): array
    {
        return sys_getloadavg();
    }

    /**
     * Get stats.
     *
     * @param
     *  $plabel: Primary label. Should be set prior to secondary label.
     * @param
     *  $slabel: Secondary label. Should be set after primary label.
     *
     * @return array|Exception
     */
    public function CheckGati($plabel, $slabel)
    {
        try {
            $time = $this->TimeDiff($plabel, $slabel);
            $usage = $this->UsageDiff($plabel, $slabel);
            $memory = $this->MemoryDiff($plabel, $slabel);
            $peak_memory = $this->GetPeakMemory($slabel);

            return array(
                'time' => $time,
                'usage' => $usage,
                'memory' => $memory,
                'peak_memory' => $peak_memory,
            );
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Get time difference.
     *
     * @param $plabel
     * @param $slabel
     *
     * @return float
     */
    protected function TimeDiff($plabel, $slabel): float
    {
        // Get values.
        $plabel .= '_time';
        $slabel .= '_time';

        return $this->$slabel - $this->$plabel;
    }

    /**
     * Get usage difference.
     *
     * @param $plabel
     * @param $slabel
     *
     * @return array
     */
    protected function UsageDiff($plabel, $slabel): array
    {
        // Get values.
        $plabel .= '_usage';
        $slabel .= '_usage';

        return $this->GetrusageDiff($this->$plabel, $this->$slabel);
    }

    /**
     * Get difference of arrays with keys intact.
     *
     * @param $arr1
     * @param $arr2
     *
     * @return array
     */
    private function GetrusageDiff($arr1, $arr2): array
    {
        $array = array();
        // Add user mode time.
        $arr1['ru_utime.tv'] = ($arr1['ru_utime.tv_usec'] / 1000000) + $arr1['ru_utime.tv_sec'];
        $arr2['ru_utime.tv'] = ($arr2['ru_utime.tv_usec'] / 1000000) + $arr2['ru_utime.tv_sec'];
        // Add system mode time.
        $arr1['ru_stime.tv'] = ($arr1['ru_stime.tv_usec'] / 1000000) + $arr1['ru_stime.tv_sec'];
        $arr2['ru_stime.tv'] = ($arr2['ru_stime.tv_usec'] / 1000000) + $arr2['ru_stime.tv_sec'];

        // Unset time splits.
        unset($arr1['ru_utime.tv_usec'], $arr1['ru_utime.tv_sec'], $arr2['ru_utime.tv_usec'], $arr2['ru_utime.tv_sec'], $arr1['ru_stime.tv_usec'], $arr1['ru_stime.tv_sec'], $arr2['ru_stime.tv_usec'], $arr2['ru_stime.tv_sec']);

        // Iterate over values.
        foreach ($arr1 as $key => $value) {
            $array[$key] = $arr2[$key] - $arr1[$key];
        }

        return $array;
    }

    /**
     * Get memory usage difference.
     *
     * @param $plabel
     * @param $slabel
     *
     * @return float|int
     */
    protected function MemoryDiff($plabel, $slabel)
    {
        // Get values.
        $plabel .= '_memory';
        $slabel .= '_memory';

        // Return value in MB.
        return ($this->$slabel - $this->$plabel) / 1024 / 1024;
    }

    /**
     * Get memory peak usage.
     *
     * @param $label
     *
     * @return float|int
     */
    protected function GetPeakMemory($label)
    {
        $label .= '_peak_memory';

        return $this->$label / 1024 / 1024;
    }

}
