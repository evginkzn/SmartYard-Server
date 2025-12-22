<?php

    /**
     * backends monitoring namespace.
     */

    namespace backends\monitoring;

    /**
     * simple monitoring class.
     */

    class simple extends monitoring
    {
        /**
         * @inheritDoc
         */

        public function deviceStatus($deviceType, $deviceId)
        {
            // Try to ping device directly
            if (is_array($deviceId) && isset($deviceId['url'])) {
                $url = parse_url($deviceId['url']);
                $host = $url['host'] ?? '';
                $port = $url['port'] ?? ($url['scheme'] === 'https' ? 443 : 80);

                if ($host) {
                    $fp = @stream_socket_client("$host:$port", $errno, $errstr, 2);
                    if ($fp) {
                        fclose($fp);
                        return [
                            "status" => "ok",
                            "message" => "Online",
                        ];
                    } else {
                        return [
                            "status" => "error",
                            "message" => "Offline",
                        ];
                    }
                }
            }

            return [
                "status" => "unknown",
                "message" => "Unknown",
            ];
        }

        /**
         * @inheritDoc
         */

        public function devicesStatus($deviceType, $hosts)
        {
            if (empty($hosts)) {
                return false;
            }

            $result = [];
            foreach ($hosts as $host) {
                $hostId = $host['hostId'] ?? null;
                if ($hostId !== null) {
                    $result[$hostId] = $this->deviceStatus($deviceType, $host);
                }
            }

            return empty($result) ? false : $result;
        }

        /**
         * @inheritDoc
         */

        public function configureMonitoring()
        {
            return false;
        }
    }
