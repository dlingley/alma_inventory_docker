<?php
// Set your Alma Shelflist API Key here, or supply it via the ALMA_SHELFLIST_API_KEY
// environment variable (recommended when using Docker / .env).
define("ALMA_SHELFLIST_API_KEY", trim(getenv("ALMA_SHELFLIST_API_KEY") ?: "*****YOUR KEY HERE *********"));
define("ALMA_ANALYTICS_API_KEY", trim(getenv("ALMA_ANALYTICS_API_KEY") ?: ALMA_SHELFLIST_API_KEY));
define("ALMA_ANALYTICS_REPORT_PATH", trim(getenv("ALMA_ANALYTICS_REPORT_PATH") ?: "/shared/Purdue University/Reports/CallNumberSortCheck"));
