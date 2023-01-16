<?php

/*
    Какие требования у данного бота?

    Для работы бота нужен домен и установленный на нем SSL-сертификат.
    Потому что работает бот через веб-хуки.
    Существуют бесплатные сертификаты, выдаваемые на 90 дней,
    и есть программа certbot, которая умеет их автоматом обновлять когда пришло время.

    Так же нужна dash-нода и 36+ гигабайт места на диске под блокчейн.
    Это полный кошелек, скачать его нужно по ссылке:
    https://github.com/dashpay/dash/releases/download/v18.1.1/dashcore-18.1.1-x86_64-linux-gnu.tar.gz
    Распаковать командой tar -xzf dashcore-18.1.1-x86_64-linux-gnu.tar.gz
    Перейти в папку dashcore-18.1.1/bin/
    и запустить командой ./dashd --usehd, чтобы сразу создать правильный HD-кошелек.
    Опция --usehd нужна только для первого запуска, пока кошелек еще не создан.
    Последующие запуски можно делать без нее.
    После этого в соседнем терминале, перейдя в папку dashcore-18.1.1/bin/
    получите seed-фразу и ключ командой:
    ./dash-cli dumphdinfo
    Эта информация понадобится для восстановления кошелька.
    Восстанавливать нужно командой
    ./dashd --usehd --mnemonic="тут ваши секретные слова"
    предварительно удалив ~/.dashcore/wallets/wallet.dat
    Чтобы остановить ноду, выполните в соседнем терминале:
    ./dash-cli stop
    После остановки в файл ~/.dashcore/dash.conf впишите две строки
    rpcuser=user1
    rpcpassword=password1
    VPS c RAM=6GB и swap=2GB работает и нода не вылетает.
    Без swap ее прибивало.
    После полной синхронизации перезапустите ноду чтобы освободить сожранную память.
    Если в процессе работы нода будет убита, то запускайте переиндексацию:
    ./dashd -reindex
    Для того чтобы нода постоянно работала, даже когда вы отключитесь от терминала,
    воспользуйтесь утилитой screen, запустите ее командой screen -S dash
    После этого запустите ноду в рабочее состояние:
    ./dashd -instantsendnotify="php /путь/к/нашему/боту/bot.php %s" -walletnotify="php /путь/к/нашему/боту/bot.php %s"
    Нода будет непосредственно уведомлять нашего бота о поступающих транзакциях.
    Но тут есть нюанс. Транзакция может быть отправлена без InstantSend и у нее будет признак 'trusted' => false
    Отключитесь от консоли комбинацией Ctrl+A d
    Последующее подключение к screen делайте командой screen -r dash
    А посмотреть список сессий можно командой screen -ls
    Как потом извлечь монеты из ноды и отправить их на другой кошелек?
    ./dash-cli getwalletinfo
    отобразит баланс. Далее выполняем команду
    ./dash-cli sendtoaddress "тут_внешний_адрес" 0.0099 "" "" true true
    Параметр после адреса это сумма, целиком пишем все что имеем,
    затем в кавычках два ненужных нам комментария для чего-то там,
    и последние true true обозначают вычесть комиссию из общей суммы и использовать инстант сенд.
    Подробнее можно узнать командой
    ./dash-cli help sendtoaddress



    Как создать бота?

    В Телеграм ищем юзера @BotFather и создаем ногово бота командой /newbot.
    Сперва указываем имя, а потом юзернейм. Юзернейм должен быть уникален и поэтому придется поподбирать.
    Впоследствии можно сменить имя боту командой /setname, чтобы оно совпадало с юзернеймом.
    Получаем токен, создаем файл config.php и вписываем его туда:
    <?php
    $bot = array (
        "token"       => "1234567890:7PqFMAFHA_ebTJ35Q1ZIZyZCtVARfoWapas",
        "dashd_url"   => "http://127.0.0.1/",
        "dashd_port"  => "9998",
        "rpcuser"     => "user1",
        "rpcpassword" => "password1",
        "pereuchet"   => false,
        "myname"      => "@dashtipbot",
    );



    Как установить бота?

    Зайдите на свой сайт по адресу https://bot.site.ru/bot.php?cmd=install
    Для удаления хука зайдите по адресу https://bot.site.ru/bot.php?cmd=uninstall

*/


// Настройки бота
require( __DIR__ . "/config.php" );


// Функция для работы с Telegram API
function telegram( $cmd, $data = [] ) {
    global $bot;
    $curl = curl_init();
    curl_setopt_array( $curl, [
        CURLOPT_URL => "https://api.telegram.org/bot{$bot['token']}/{$cmd}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => http_build_query( $data ),
    ] );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    return json_decode( $resp, true );
}


// Функция для работы с Dash RPC
function rpc( $data = [] ) {
    global $bot;
    $data["jsonrpc"] = "1.0";
    $data["id"] = microtime();
    $curl = curl_init();
    $json = json_encode( $data );
    curl_setopt_array( $curl, [
        CURLOPT_URL => $bot["dashd_url"],
        CURLOPT_PORT => $bot["dashd_port"],
        CURLOPT_USERPWD => "{$bot['rpcuser']}:{$bot['rpcpassword']}",
        CURLOPT_HEADER => 0,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 1,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [ "Content-type: text/plain", "Content-length: " . strlen( $json ) ],
    ] );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    return json_decode( $resp, true );
}


// Логика дяльнейшего кода делится на три части.
// Информация может поступать к боту из трех мест.
// Во-первых, это две команды через адресную строку браузера install и uninstall, через $_GET.
// Во-вторых, уведомления от Ноды, о входящих и исходящих монетах, через параметр командной строки $argv.
// В-третьих, от Телеграма, через php://input.
// Все три обработчика расположены друг за другом.
// Если один из них не находит для себя информацию, то исполнение продолжается и следующий ищет свое.
// Это такая вот подсказочка, чтобы не блуждать по коду.


// Команды управления ботом через адресную строку браузера
// Установка и удаление web-хука
// https://bot.site.ru/bot.php?cmd=install
// https://bot.site.ru/bot.php?cmd=uninstall
if ( isset( $_GET["cmd"] ) ) {
    switch ( $_GET["cmd"] ) {

        case "uninstall":
            $answer = telegram( "setWebhook" );
            echo( var_export( $answer, true ) );
            return;
        break;

        case "install":
            $answer = telegram(
                "setWebhook",
                [
                    "url" => "https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}"
                ]
            );
            echo( var_export( $answer, true ) );
            return;
        break;

    }
}


// Считываем сохраненное состояние
// Все что нам нужно для работы бота, сохраняем в самом обычном php-файле
// в виде массива, который считывается командой include и все данные
// возвращаются на свои места.
$data_php = __DIR__ . "/data.php";
if ( file_exists( $data_php ) && $fp = fopen( $data_php, "r" ) ) {
    // Блокируем файл чтобы не прочитать мусор в тот момент когда
    // работает команда записи в файл
    flock( $fp, LOCK_SH );
    include( $data_php );
    // разблокируем
    flock( $fp, LOCK_UN );
    fclose( $fp );
} else {
    $data = [
        "users"  => [], // Пользователи
        "inputs" => [], // Адреса для пополнения счета пользователя
    ];
}


// А это функция для сброса состояния на диск
function save_data() {
    global $data;
    $data_php = __DIR__ . "/data.php";
    $backup   = __DIR__ . "/backups/data." . microtime( true ) . ".php";
    if ( file_exists( $data_php ) && $fp = fopen( $data_php, "r" ) ) {
        flock( $fp, LOCK_SH );
        copy( $data_php, $backup );
        flock( $fp, LOCK_UN );
        fclose( $fp );
    }
    $export = '<?php $data = ' . var_export( $data, true) . ";\n";
    // С блокировкой чтобы предотвратить одновременную запись
    $r = file_put_contents( $data_php, $export, LOCK_EX );
    return $r;
}


// Функция для ведения отладочного лога
function debug_log( $var, $label = "" ) {
    if ( is_array( $var ) ) {
        $str = var_export( $var, true );
    } else {
        $str = $var;
    }
    $log = date( "Y-m-d H:i:s " ) . "{$label}{$str}\n";
    file_put_contents( __DIR__ . "/debug.log", $log, FILE_APPEND );
}


// Функция для ведения денежного лога
function money_log( $str ) {
    $log = date( "Y-m-d H:i:s " ) . "{$str}\n";
    file_put_contents( __DIR__ . "/money.log", $log, FILE_APPEND );
}


// Функция обновления курса
$currencies = [ "usd", "rub", "uah", "dash", "mdash" ];
function update_curr() {
    global $data, $currencies;
    if ( ! isset( $data["update_curr"] ) ) {
        $data["update_curr"] = 0;
    }
    if ( $data["update_curr"] < time() - 5*60 ) {
        $curs = [];
        foreach( $currencies as $cur ) {
            if ( $cur !== "dash" && $cur !== "mdash" ) {
                $curs[] = "dash" . $cur;
            }
        }
        $curs = implode( ",", $curs );
        $json = file_get_contents( "https://rates2.dashretail.org/rates?source=dashretail&symbol={$curs}" );
        $arr = json_decode( $json, true );
        if ( is_array( $arr ) ) {
            foreach( $arr as $cur ) {
                $cur_lc = strtolower( $cur["quoteCurrency"] );
                $data["curr"][ $cur_lc ] = $cur;
                
                // Сохраняем совместимость пока
                if ( $cur_lc === "usd" ) {
                    $data["dashusd"] = [
                        "price" => $data["curr"][ $cur_lc ]["price"],
                        "date" => time(),
                    ];
                }
            }
            $data["curr"]["dash"]["price"] = 1;
            $data["curr"]["mdash"]["price"] = 1000;
            $data["update_curr"] = time();
            // Сохраняем данные
            save_data();
        }
    }
}


function balance_format( $dash ) {
    global $data, $currencies;

    if ( $dash == 0 ) {
        $dash_f = "0";
    } else {
        $dash_f = rtrim( number_format( $dash, 8, ".", "" ), "0" );
    }

    foreach( $currencies as $cur ) {
        if ( $cur !== "dash" && $cur !== "mdash" ) {
            $inval = round( $dash * $data["curr"][ $cur ]["price"], 2 );
            $a[] = "{$inval} {$cur}";
        }
    }
    return "Баланс: {$dash_f} dash\n" . implode( ", ", $a );
}


function check_balances() {
    global $data;
    $sum = 0;
    foreach( $data["users"] as $user ) {
        if ( isset( $user["balance"] ) ) {
            $sum = round( $sum + $user["balance"], 8 );
        }
    }
    $wallet = rpc( [ "method" => "getwalletinfo", "params" => [] ] );
    if ( $wallet["result"]["balance"] >= $sum ) {
        return true;
    }
    debug_log( "Баланс ноды {$wallet["result"]["balance"]}" );
    debug_log( "Баланс кошельков {$sum}" );
    return false;
}



// Работаем с входными данными



// Входные данные от Ноды
// Мы получаем id транзакции с которой затем и работаем.
// Сумма транзакции может быть и отрицательной, если это уведомление о том,
// что был перевод не в кошелек, а из него.
if ( isset( $argv ) ) {
    if ( count( $argv ) === 2 ) {
        //debug_log( $argv[1], '$argv[1] = ' );
        $txid = $argv[1];
        $tx = rpc( [ "method" => "gettransaction", "params" => [ $txid ] ] );
        //debug_log( $tx, '$tx = ' );

        // Обрабатываем транзакцию
        if ( $tx["error"] !== NULL ) {
            // Пришло уведомление об ошибке

        } elseif ( $tx["result"] !== NULL ) {

            $tx = $tx["result"];

            // Игнорируем уведомления о исходящих транзакциях
            if ( $tx["amount"] < 0 ) {
                exit;
            }

            // Условия для зачисления
            // Именно такие, потому что аж 4 уведомления может быть
            $is_confirm   = $tx["instantlock"] === true  && $tx["confirmations"] === 0;
            $nois_confirm = $tx["instantlock"] === false && $tx["confirmations"] === 1;

            // Извлекаем адрес на который пришел перевод
            $addr = $tx["details"][0]["address"];
            if ( ! isset( $data["inputs"][ $addr ] ) && ( $is_confirm || $nois_confirm ) ) {
                money_log( "Неопознанное поступление на адрес {$addr} {$tx["amount"]} dash" );
                debug_log( $tx, '$tx = ' );
                exit;
            }

            // Определяем id юзера
            $uid  = $data["inputs"][ $addr ];
            
            $already_added = $tx["txid"] === $data["users"][ $uid ]["txid"];

            if ( ! $already_added && ( $is_confirm || $nois_confirm ) ) {

                $old_balance = $data["users"][ $uid ]["balance"];
                // Добавляем ему баланса 
                $data["users"][ $uid ]["balance"] += $tx["amount"];
                $data["users"][ $uid ]["txid"]     = $tx["txid"];
                save_data();
                $uname   = $data["users"][ $uid ]["first_name"];
                money_log( "{$uname} ({$uid}) с балансом {$old_balance} dash, пополнил баланс на {$tx["amount"]} dash и имеет {$data['users'][$uid]['balance']} dash" );
                money_log( "OK" );

                // Уведомляем о поступлении
                if ( isset( $data["users"][ $uid ]["chat"] ) ) {

                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $data["users"][ $uid ]["chat"],
                            "text"    => "Баланс пополнен на {$tx["amount"]} dash.\nБаланс: {$data["users"][ $uid ]["balance"]} dash",
                        ]
                    );

                }

            }

        }
    } else {
        debug_log( "Неверное количество аргументов:" );
        debug_log( $argv, '$argv = ' );
    }

    return;
}


// Входные данные от Телеграма
$input_raw = file_get_contents( "php://input" );

if ( empty( $input_raw ) ) {
    return;
}

// Преобразуем входные данные в обычный массив
$input = json_decode( $input_raw, true ); // NULL если не парсится

if ( ! is_array( $input ) ) {
    return;
}

// Для отладки
//debug_log( $input, 'Telegram $input = ' );


// Диалог с пользователем

if ( ! empty( $input["message"] ) && isset( $input["message"]["text"] ) ) {

    if ( $input["message"]["chat"]["type"] === "private" ) {
    // Секция личного общения с ботом

        $uid     = $input["message"]["from"]["id"];
        $uname   = $input["message"]["from"]["first_name"];
        $chat_id = $input["message"]["chat"]["id"];

        // Переучет
        if ( $bot["pereuchet"] ) {
            $r = telegram(
                "sendMessage",
                [
                    "chat_id" => $chat_id,
                    "text"    => "В данный момент бот переведен в режим переучета, для каких-то работ.",
                ]
            );
            exit;
        }

        if ( ! check_balances() ) {
            $r = telegram(
                "sendMessage",
                [
                    "chat_id" => $chat_id,
                    "text"    => "Бот сверяет балансы. Попробуйте позже.",
                ]
            );
            exit;
        }
        
        $args = preg_split( "/\s+/", $input["message"]["text"] );

        switch( $args[0] ) {

            case "/start":

                if ( ! isset( $data["users"][ $uid ]["input"] ) ) {
                    
                    // Получаем у ноды адрес для оплаты
                    // На него будем принимать монеты для пополнения счета юзера
                    $rpc = rpc( [ "method" => "getnewaddress", "params" => [] ] );
                    //debug_log( $rpc, 'Dash $addr = ' );
                    if ( $rpc["error"] !== NULL ) {
                        exit;
                    }
                    $addr = $rpc["result"];

                    $data["users"][ $uid ]            = $input["message"]["from"];
                    $data["users"][ $uid ]["chat"]    = $chat_id;
                    $data["users"][ $uid ]["date"]    = date( "Y-m-d H:i:s", $input["message"]["date"] );
                    $data["users"][ $uid ]["input"]   = $addr;
                    if ( ! isset( $data["users"][ $uid ]["balance"] ) ) {
                        $data["users"][ $uid ]["balance"] = 0;
                    }

                    $data["inputs"][ $addr ]          = $uid;

                    save_data();
                }
                //debug_log( $data["users"], '$users = ' );

                if ( isset( $data["users"][ $uid ]["output"] ) ) {
                    $output_addr = "Вы установили адрес для вывода средств:\n{$data['users'][$uid]['output']}";
                } else {
                    $output_addr = "Вы не установили адрес для вывода средств.";
                }

                update_curr();

                $balance = balance_format( $data["users"][ $uid ]["balance"] );

                $user_name = $input["message"]["from"]["first_name"];
                $start_msg = "Привет, {$user_name}!

Это бот создан для донатов в криптовалюте Dash.
С помощью него вы можете раздавать и принимать донаты.
Это кастодиальный бот (ваши средства хранятся у чужого дяди), не храните на балансе суммы, которые жалко потерять.
Ввод и вывод на счет осуществляются с минимальными комиссиями сети, поэтому сразу выводите излишки.

/myaddr XkH6uBT9aG... - добавить в бота адрес своего кошелька для вывода средств

/withdrawal 0.2 - вывести указанную сумму на адрес своего кошелька

/dashtip 2 usd      - отправить 2 доллара
/dashtip 3 rub      - отправить 3 рубля
/dashtip 4 uah      - отправить 4 гривны
/dashtip 0.003 dash - отправить в дешах
/dashtip 3 mdash    - тысячные деша, т.е. тоже 0.003

/balance - узнать баланс

Вы можете пополнить счет отправив монеты на адрес:
{$data['users'][$uid]['input']}
Это ваш индивидуальный адрес для пополнения счета.

Копируйте все сообщение, мобильный dash-кошелек умеет распознавать адреса в сообщениях и возмет первый из них для перевода на него.

{$output_addr}

{$balance}";

                $r = telegram(
                    "sendMessage",
                    [
                        "chat_id" => $chat_id,
                        "text"    => $start_msg,
                    ]
                );

            break; // /start

            case "/balance":

                update_curr();

                $balance = balance_format( $data["users"][ $uid ]["balance"] );

                $r = telegram(
                    "sendMessage",
                    [
                        "chat_id" => $chat_id,
                        "text"    => $balance,
                    ]
                );
                
            break;

            case "/myaddr":
                if ( ! isset( $args[1] ) ) {
                    exit;
                }
                if ( preg_match( "/[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{25,34}/", $args[1] ) ) {

                    $data["users"][ $uid ]["output"] = $args[1];
                    save_data();
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Адрес для вывода задан",
                        ]
                    );

                } else {
                    
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Это не похоже на адрес",
                        ]
                    );
                }
            break;

            case "/withdrawal":

                // Ошибка адреса для вывода
                // Это когда отправили не зарегистрированному пользователю, например
                if ( ! isset( $data["users"][ $uid ]["output"] ) ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не установлен адрес для вывода",
                        ]
                    );
                    exit;
                }

                if ( isset( $args[1] ) && is_numeric( $args[1] ) ) {

                    $sum = round( (float) $args[1], 8 );
                    if ( $sum <= 0 || $sum > $data["users"][ $uid ]["balance"] ) {
                        $r = telegram(
                            "sendMessage",
                            [
                                "chat_id" => $chat_id,
                                "text"    => "Неверная сумма",
                            ]
                        );
                        exit;
                    }

                    money_log( "{$uname} ({$uid}) с балансом {$data['users'][$uid]['balance']} выводит на {$data['users'][$uid]['output']} {$sum} dash" );

                    $send = rpc( [
                        "method" => "sendtoaddress",
                        "params" => [
                            $data["users"][ $uid ]["output"], $sum, "", "", true, true
                        ]
                    ] );

                    if ( $send["result"] !== NULL ) {
                        // Тут вопрос возник. Ввожу себе на счет 0.003 dash
                        // Вывожу 0.00299999 dash
                        // Нода самопроизвольно округляет до 0.003 и выводит под ноль
                        // Поэтому нужно получить транзакцию и брать из нее сколько фактически ушло
                        $tx = rpc( [ "method" => "gettransaction", "params" => [ $send["result"] ] ] );
                        if ( $tx["result"] === NULL ) {
                            debug_log( $tx, '$tx = ' );
                            $msg = "Ошибка отправки";
                            money_log( $msg );
                        } else {
                            $sum = $tx["result"]["amount"] + $tx["result"]["fee"];

                            $data["users"][ $uid ]["balance"] = round( $data["users"][ $uid ]["balance"] + $sum, 8 );
                            save_data();
                            $msg = "Отправлено";
                            money_log( "У {$uname} ({$uid}) баланс {$data['users'][$uid]['balance']} dash" );
                            money_log( $msg );
                        }
                    } else {
                        $msg = "Ошибка отправки";
                        money_log( $msg );
                    }

                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => $msg,
                        ]
                    );
                    
                } else {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не распознано число",
                        ]
                    );
                }
            break;

        }

    
    // Секция команд в групповом чате
    } elseif ( isset( $input["message"]["text"] ) 
            && isset( $input["message"]["reply_to_message"] )
            && substr( $input["message"]["text"], 0, 8 ) === "/dashtip" ) {
        
        //debug_log( $input, 'Telegram $input = ' );

        $uid     = $input["message"]["from"]["id"];
        $to_uid  = $input["message"]["reply_to_message"]["from"]["id"];
        $chat_id = $input["message"]["chat"]["id"];

        // Переучет
        if ( $bot["pereuchet"] ) {
            $r = telegram(
                "sendMessage",
                [
                    "chat_id" => $chat_id,
                    "text"    => "В данный момент бот переведен в режим переучета, для каких-то работ.",
                ]
            );
            exit;
        }

        if ( ! check_balances() ) {
            $r = telegram(
                "sendMessage",
                [
                    "chat_id" => $chat_id,
                    "text"    => "Бот сверяет балансы. Попробуйте позже.",
                ]
            );
            exit;
        }
        
        $args = preg_split( "/\s+/", $input["message"]["text"] );

        switch( $args[0] ) {

            case "/dashtip":
                // Проверить что отправляющий зарегистрирован
                if ( ! isset( $data["users"][ $uid ]["balance"] ) ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Для отправления переводов пользователь должен начать общение с ботом {$bot['myname']} и иметь средства на балансе.",
                        ]
                    );
                    exit;
                }

                // Проверить что юзер зарегистрирован
                if ( ! isset( $data["users"][ $to_uid ]["balance"] ) ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Перевод отклонен. Для получения переводов пользователь должен начать общение с ботом {$bot['myname']}",
                        ]
                    );
                    exit;
                }

                // Количество параметров
                if ( count( $args ) < 3 ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Укажите сумму и валюту через пробел. Например, 10 usd.",
                        ]
                    );
                    exit;
                }

                // Распознаем сумму
                if ( ! preg_match( "/^(\d+(\.\d+)?)\$/", $args[1] ) ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не распознана сумма: {$args[1]}",
                        ]
                    );
                    exit;
                }

                $sum = $args[1];
                $cur = $args[2];

                if ( ! in_array( $cur, $currencies ) ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не поддерживаемая валюта: {$cur}",
                        ]
                    );
                    exit;
                }

                update_curr();

                $sum = round( $sum / $data["curr"][ $cur ]["price"], 8 );
                if ( $cur !== "dash" && $cur !== "mdash" ) {
                    $incur = " (" . round( $sum * $data["curr"][ $cur ]["price"], 2) . " {$cur})";
                } else {
                    $incur = "";
                }

                if ( $data["users"][ $uid ]["balance"] < $sum ) {
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не хватает средств на балансе.",
                        ]
                    );
                    exit;
                }

                /*
                $r = telegram(
                    "sendMessage",
                    [
                        "chat_id" => $chat_id,
                        "text"    => "Идут работы. Команда не выполняется. {$sum} dash $incur",
                    ]
                );
                exit;
                */

                $do = round( $data["users"][ $uid ]["balance"] + $data["users"][ $to_uid ]["balance"], 8 );;

                money_log( "{$data['users'][$uid]['first_name']} ($uid) с балансом {$data['users'][$uid]['balance']} dash" );
                money_log( "отправляет {$sum} {$cur}{$incur} пользователю {$data['users'][$to_uid]['first_name']} ($to_uid) с балансом {$data['users'][$to_uid]['balance']} dash" );
                
                $data["users"][ $uid ]["balance"]    = round( $data["users"][ $uid ]["balance"]    - $sum, 8 );
                $data["users"][ $to_uid ]["balance"] = round( $data["users"][ $to_uid ]["balance"] + $sum, 8 );

                $posle = round( $data["users"][ $uid ]["balance"] + $data["users"][ $to_uid ]["balance"], 8 );;

                if ( $do === $posle ) {
                    save_data();
                    money_log( "Теперь у {$data['users'][$uid]['first_name']} ($uid) баланс {$data['users'][$uid]['balance']} dash" );
                    money_log( "А у {$data['users'][$to_uid]['first_name']} ($to_uid) баланс {$data['users'][$to_uid]['balance']} dash" );
                    money_log( "OK" );

                    // В общий чат
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Отправлено {$sum} dash {$incur} от {$data['users'][$uid]['first_name']} к {$data['users'][$to_uid]['first_name']}",
                        ]
                    );

                    // Юзеру которому зачислено
                    $balance = balance_format( $data["users"][ $to_uid ]["balance"] );
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $data["users"][$to_uid]["chat"],
                            "text"    => "{$data['users'][$uid]['first_name']} прислал вам {$sum} dash{$incur}.\n$balance",
                        ]
                    );

                    // Юзеру который отправил
                    $balance = balance_format( $data["users"][ $uid ]["balance"] );
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $data["users"][$uid]["chat"],
                            "text"    => "Вы отправили {$data['users'][$to_uid]['first_name']} {$sum} dash{$incur}.\n$balance",
                        ]
                    );
                } else {
                    money_log( "--- Не отправлено. Беда с округлениями. ---" );
                    $r = telegram(
                        "sendMessage",
                        [
                            "chat_id" => $chat_id,
                            "text"    => "Не отправлено. Беда с округлениями.",
                        ]
                    );
                }


            break;

        }

    }

}


