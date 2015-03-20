# weekly-status

Creates weekly status emails with time spent information from toggl.  Aims to be suitable for forwarding to clients.

# installation / configuration

    $ git clone https://github.com/ordinaryexperts/weekly-status.git
    $ cd weekly-status
    $ composer install
    $ cp config.json.dist config.json
    $ emacs config.json

# usage

    $ php send-weekly-status.php -v -s 2015-01-01 -e 2015-01-31
    $ ls -l build
