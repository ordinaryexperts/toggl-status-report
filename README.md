# toggl-status-reports

Creates Excel status reports per client with time spent information from toggl.  Aims to be suitable for forwarding to clients.

# installation / configuration

    $ git clone https://github.com/ordinaryexperts/toggl-status-report.git
    $ cd toggl-status-report
    $ composer install
    $ cp config.json.dist config.json
    $ emacs config.json

# usage

    $ php generate-status.php -v -s 2015-01-01 -e 2015-01-31
    $ ls -l build
