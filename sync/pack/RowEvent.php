<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 15/11/7
 * Time: 下午9:15
 */
class RowEvent extends BinLogEvent
{


    public static function rowInit(BinLogPack $pack, $event_type, $size)
    {
        parent::_init($pack, $event_type, $size);
        self::$TABLE_ID = self::readTableId();

        if (in_array(self::$EVENT_TYPE, [ConstEventType::DELETE_ROWS_EVENT_V2, ConstEventType::WRITE_ROWS_EVENT_V2, ConstEventType::UPDATE_ROWS_EVENT_V2])) {
            self::$FLAGS = unpack('S', self::$PACK->read(2))[1];

            self::$EXTRA_DATA_LENGTH = unpack('S', self::$PACK->read(2))[1];

            self::$EXTRA_DATA = self::$PACK->read(self::$EXTRA_DATA_LENGTH / 8);

        } else {
            self::$FLAGS = unpack('S', self::$PACK->read(2))[1];
        }

        // Body
        self::$COLUMNS_NUM = self::$PACK->readCodedBinary();
    }


    public static function tableMap(BinLogPack $pack, $event_type)
    {
        parent::_init($pack, $event_type);

        self::$TABLE_ID = self::readTableId();

        self::$FLAGS = unpack('S', self::$PACK->read(2))[1];

        $data = [];
        $data['schema_length'] = unpack("C", $pack->read(1))[1];

        $data['schema_name'] = self::$SCHEMA_NAME = $pack->read($data['schema_length']);

        // 00
        self::$PACK->advance(1);

        $data['table_length'] = unpack("C", self::$PACK->read(1))[1];
        $data['table_name'] = strtolower(self::$TABLE_NAME = $pack->read($data['table_length']));


        // 00
        self::$PACK->advance(1);

        self::$COLUMNS_NUM = self::$PACK->readCodedBinary();

        //
        $column_type_def = self::$PACK->read(self::$COLUMNS_NUM);


        // 避免重复读取 表信息
        if (isset(self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME]['table_id'])
            && self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME]['table_id']== self::$TABLE_ID) {
            return $data;
        }


        self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME] = array(
            'schema_name'=> $data['schema_name'],
            'table_name' => $data['table_name'],
            'table_id'   => self::$TABLE_ID
        );


        self::$PACK->readCodedBinary();


        // fields 相应属性
        $colums = DBHelper::getFields($data['schema_name'], $data['table_name']);

        self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME]['fields'] = [];

        for ($i = 0; $i < strlen($column_type_def); $i++) {
			$type = ord($column_type_def[$i]);
			if(!isset($colums[$i])){
				Log::warn(var_export($colums, true).var_export($data, true), 'tableMap', Config::$LOG['binlog']['error']);
			}
            self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME]['fields'][$i] = BinLogColumns::parse($type, $colums[$i], self::$PACK);

        }

        return $data;


    }

    public static function addRow(BinLogPack $pack, $event_type, $size)
    {
        self::rowInit($pack, $event_type, $size);

        $result = [];
        // ？？？？
        //$result['extra_data'] = getData($data, );
//        $result['columns_length'] = unpack("C", self::$PACK->read(1))[1];
        //$result['schema_name']   = getData($data, 29, 28+$result['schema_length'][1]);
        $len = (int)((self::$COLUMNS_NUM + 7) / 8);


        $result['bitmap'] = self::$PACK->read($len);

        //nul-bitmap, length (bits set in 'columns-present-bitmap1'+7)/8
        $value['add'] = self::_getAddRows($result, $len);
        return $value;
    }

    public static function delRow(BinLogPack $pack, $event_type, $size)
    {
        self::rowInit($pack, $event_type, $size);

        $result = [];
        // ？？？？
        //$result['extra_data'] = getData($data, );
//        $result['columns_length'] = unpack("C", self::$PACK->read(1))[1];
        //$result['schema_name']   = getData($data, 29, 28+$result['schema_length'][1]);
        $len = (int)((self::$COLUMNS_NUM + 7) / 8);


        $result['bitmap'] = self::$PACK->read($len);


        //nul-bitmap, length (bits set in 'columns-present-bitmap1'+7)/8
        $value['del'] = self::_getDelRows($result, $len);

        return $value;
    }

    public static function updateRow(BinLogPack $pack, $event_type, $size)
    {

        self::rowInit($pack, $event_type, $size);

        $result = [];
        // ？？？？
        //$result['extra_data'] = getData($data, );
//        $result['columns_length'] = unpack("C", self::$PACK->read(1))[1];
        //$result['schema_name']   = getData($data, 29, 28+$result['schema_length'][1]);
        $len = (int)((self::$COLUMNS_NUM + 7) / 8);


        $result['bitmap1'] = self::$PACK->read($len);

        $result['bitmap2'] = self::$PACK->read($len);


        //nul-bitmap, length (bits set in 'columns-present-bitmap1'+7)/8
        $value['update'] = self::_getUpdateRows($result, $len);

        return $value;
    }

    public static function BitGet($bitmap, $position)
    {
        $bit = $bitmap[intval($position / 8)];

        if (is_string($bit)) {

            $bit = ord($bit);
        }

        return $bit & (1 << ($position & 7));
    }

    public static function _is_null($null_bitmap, $position)
    {
        $bit = $null_bitmap[intval($position / 8)];
        if (is_string($bit)) {
            $bit = ord($bit);
        }


        return $bit & (1 << ($position % 8));
    }

    private static function _read_string($size, $column)
    {
        $string = self::$PACK->read_length_coded_pascal_string($size);
        if ($column['character_set_name']) {
            //string = string . decode(column . character_set_name)
        }
        return $string;
    }

    private static function _read_column_data($cols_bitmap, $len)
    {
        $values = [];

        //$l = (int)(($len * 8 + 7) / 8);
        $l = (int)((self::bitCount($cols_bitmap) + 7) / 8);

        # null bitmap length = (bits set in 'columns-present-bitmap'+7)/8
        # See http://dev.mysql.com/doc/internals/en/rows-event.html


        $null_bitmap = self::$PACK->read($l);

        $nullBitmapIndex = 0;
        foreach (self::$TABLE_MAP[self::$SCHEMA_NAME][self::$TABLE_NAME]['fields'] as $i => $value) {
            $column = $value;
            $name = $value['name'];
            $unsigned = $value['unsigned'];


            if (self::BitGet($cols_bitmap, $i) == 0) {
                $values[$name] = null;
                continue;
            }

            if (self::_is_null($null_bitmap, $nullBitmapIndex)) {
                $values[$name] = null;
            } elseif ($column['type'] == ConstFieldType::TINY) {
                if ($unsigned) {
                    $values[$name] = unpack("C", self::$PACK->read(1))[1];
                } else {
                    $values[$name] = unpack("c", self::$PACK->read(1))[1];
                }
            } elseif ($column['type'] == ConstFieldType::SHORT) {
                if ($unsigned) {
                    $values[$name] = unpack("S", self::$PACK->read(2))[1];
                } else {
                    $values[$name] = unpack("s", self::$PACK->read(2))[1];
                }
            } elseif ($column['type'] == ConstFieldType::LONG) {
                if ($unsigned) {
                    $values[$name] = unpack("I", self::$PACK->read(4))[1];
                } else {
                    $values[$name] = unpack("i", self::$PACK->read(4))[1];
                }
            } elseif ($column['type'] == ConstFieldType::INT24) {
                if ($unsigned) {
                    $values[$name] = self::$PACK->read_uint24();
                } else {
                    $values[$name] = self::$PACK->read_int24();
                }
            } elseif ($column['type'] == ConstFieldType::FLOAT) {
                $values[$name] = unpack("f", self::$PACK->read(4))[1];
            }
            elseif ($column['type'] == ConstFieldType::DOUBLE) {
                $values[$name] = unpack("d", self::$PACK->read(8))[1];
            } elseif ($column['type'] == ConstFieldType::VARCHAR ||
                $column['type'] == ConstFieldType::STRING
            ) {
                if ($column['max_length'] > 255) {
                    $values[$name] = self::_read_string(2, $column);
                } else {
                    $values[$name] = self::_read_string(1, $column);
                }
            } elseif ($column['type'] == ConstFieldType::NEWDECIMAL) {
                $values[$name] = self::_read_new_decimal($column);
            } elseif ($column['type'] == ConstFieldType::BLOB) {
                //ok
                $values[$name] = self::_read_string($column['length_size'], $column);
            } elseif ($column['type'] == ConstFieldType::DATETIME) {
                $values[$name] = self::_read_datetime();
            } elseif ($column['type'] == ConstFieldType::DATETIME2) {
                //ok
                $values[$name] = self::_read_datetime2($column);
            }elseif ($column['type'] == ConstFieldType::TIME2) {
                $values[$name] = self::_read_time2($column);
            } elseif ($column['type'] == ConstFieldType::TIMESTAMP2){
                //ok
                $time = date('Y-m-d H:i:m',self::$PACK->read_int_be_by_size(4));
                // 微妙
                $micro = self::_add_fsp_to_time($column);
                if($micro) {
                    $time .= '.' . self::_add_fsp_to_time($column);
                }
                $values[$name] = $time;
            }
            elseif ($column['type'] == ConstFieldType::DATE) {
                $values[$name] = self::_read_date();
            }
            elseif ($column['type'] == ConstFieldType::TIMESTAMP) {
                $values[$name] = date('Y-m-d H:i:s', self::$PACK->readUint32());
            } elseif ($column['type'] == ConstFieldType::LONGLONG) {
                if ($unsigned) {
                    $values[$name] = self::$PACK->readUint64();
                } else {
                    $values[$name] = self::$PACK->readInt64();
                }

            } elseif($column['type'] == ConstFieldType::ENUM) {
                $values[$name] = $column['enum_values'][self::$PACK->read_uint_by_size($column['size']) - 1];
            } else {
                echo $column['type'] . " type(ConstFieldType.php) have not been support ,if need feedback";exit;
            }
            /*
              elseif ($column['type'] == ConstFieldType::TIME:
                $values[$name] = self.__read_time()
            elseif ($column['type'] == ConstFieldType::YEAR:
                $values[$name] = self::$PACK->read_uint8() + 1900
            elseif ($column['type'] == ConstFieldType::SET:
                # We read set columns as a bitmap telling us which options
                # are enabled
                bit_mask = self::$PACK->read_uint_by_size(column.size)
                $values[$name] = set(
                    val for idx, val in enumerate(column.set_values)
                if bit_mask & 2 ** idx
                ) or None

            elseif ($column['type'] == ConstFieldType::BIT:
                $values[$name] = self.__read_bit(column)
            elseif ($column['type'] == ConstFieldType::GEOMETRY:
                $values[$name] = self::$PACK->read_length_coded_pascal_string(
                        column.length_size)
            else:
                raise NotImplementedError("Unknown MySQL column type: %d" %
                    (column.type))
            */
            $nullBitmapIndex += 1;
        }
        $values['table_name'] = self::$TABLE_NAME;
        return $values;
    }


    private static function _read_datetime()
    {
        $value = self::$PACK->readUint64();
        if ($value == 0)  # nasty mysql 0000-00-00 dates
            return null;

        $date = $value / 1000000;
        $time = (int)($value % 1000000);

        $year = (int)($date / 10000);
        $month = (int)(($date % 10000) / 100);
        $day = (int)($date % 100);
        if ($year == 0 or $month == 0 or $day == 0)
            return null;

        return $year.'-'.$month.'-'.$day .' '.intval($time / 10000).':'.intval(($time % 10000) / 100).':'.intval($time % 100);

    }
	
    private static function _read_date() {
        $time = self::$PACK->readUint24();

        if ($time == 0)  # nasty mysql 0000-00-00 dates
            return null;

        $year = ($time & ((1 << 15) - 1) << 9) >> 9;
        $month = ($time & ((1 << 4) - 1) << 5) >> 5;
        $day = ($time & ((1 << 5) - 1));
        if ($year == 0 || $month == 0 || $day == 0)
            return null;

        return $year.'-'.$month.'-'.$day;
    }

    private static function _read_time2($column) {
        /*
        https://dev.mysql.com/doc/internals/en/date-and-time-data-type-representation.html
        TIME encoding for nonfractional part:

         1 bit sign    (1= non-negative, 0= negative)
         1 bit unused  (reserved for future extensions)
        10 bits hour   (0-838)
         6 bits minute (0-59)
         6 bits second (0-59)
        ---------------------
        24 bits = 3 bytes
        */
        $data = self::$PACK->read_int_be_by_size(3);

        $sign = 1;
        if (self::_read_binary_slice($data, 0, 1, 24) ) {
        } else {
            $sign = -1;
        }
        if ($sign == -1) {
            # negative integers are stored as 2's compliment
            # hence take 2's compliment again to get the right value.
            $data = ~$data + 1;
        }

        $hours=$sign*self::_read_binary_slice($data, 2, 10, 24);
        $minutes=self::_read_binary_slice($data, 12, 6, 24);
        $seconds=self::_read_binary_slice($data, 18, 6, 24);
        $microseconds=self::_add_fsp_to_time($column);
        $t = $hours.':'.$minutes.':'.$seconds;
        if($microseconds) {
            $t .= '.'.$microseconds;
        }
        return $t;
    }

    private static function  _read_datetime2($column) {
        /*DATETIME

        1 bit  sign           (1= non-negative, 0= negative)
        17 bits year*13+month  (year 0-9999, month 0-12)
         5 bits day            (0-31)
         5 bits hour           (0-23)
         6 bits minute         (0-59)
         6 bits second         (0-59)
        ---------------------------
        40 bits = 5 bytes
        */
        $data = self::$PACK->read_int_be_by_size(5);

        $year_month = self::_read_binary_slice($data, 1, 17, 40);


        $year=(int)($year_month / 13);
        $month=$year_month % 13;
        $day=self::_read_binary_slice($data, 18, 5, 40);
        $hour=self::_read_binary_slice($data, 23, 5, 40);
        $minute=self::_read_binary_slice($data, 28, 6, 40);
        $second=self::_read_binary_slice($data, 34, 6, 40);
        if($hour < 10) {
            $hour ='0'.$hour;
        }
        if($minute < 10) {
            $minute = '0'.$minute;
        }
        if($second < 10) {
            $second = '0'.$second;
        }
        $time = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second;
        $microsecond = self::_add_fsp_to_time($column);
        if($microsecond) {
            $time .='.'.$microsecond;
        }
        return $time;
    }

    private static function _read_binary_slice($binary, $start, $size, $data_length) {
        /*
        Read a part of binary data and extract a number
        binary: the data
        start: From which bit (1 to X)
        size: How many bits should be read
        data_length: data size
        */
        $binary = $binary >> $data_length - ($start + $size);
        $mask = ((1 << $size) - 1);
        return $binary & $mask;
    }

    private static function _add_fsp_to_time($column)
    {
        /*Read and add the fractional part of time
            For more details about new date format:
            http://dev.mysql.com/doc/internals/en/date-and-time-data-type-representation.html
            */
        $read = 0;
        $time = '';
        if( $column['fsp'] == 1 or $column['fsp'] == 2)
            $read = 1;
        elseif($column['fsp'] == 3 or $column['fsp'] == 4)
            $read = 2;
        elseif ($column ['fsp'] == 5 or $column['fsp'] == 6)
            $read = 3;
        if ($read > 0) {
            $microsecond = self::$PACK->read_int_be_by_size($read);
            if ($column['fsp'] % 2)
                $time = (int)($microsecond / 10);
            else
                $time = $microsecond;
        }
        return $time;
    }

    private static function _read_new_decimal($column) {
        #Read MySQL's new decimal format introduced in MySQL 5"""

        # This project was a great source of inspiration for
        # understanding this storage format.
        # https://github.com/jeremycole/mysql_binlog

        $digits_per_integer = 9;
        $compressed_bytes = [0, 1, 1, 2, 2, 3, 3, 4, 4, 4];
        $integral = ($column['precision'] - $column['decimals']);
        $uncomp_integral = intval($integral / $digits_per_integer);
        $uncomp_fractional = intval($column['decimals'] / $digits_per_integer);
        $comp_integral = $integral - ($uncomp_integral * $digits_per_integer);
        $comp_fractional = $column['decimals'] - ($uncomp_fractional * $digits_per_integer);

        # Support negative
        # The sign is encoded in the high bit of the the byte
        # But this bit can also be used in the value
        $value = self::$PACK->readUint8();
        if ( ($value & 0x80) != 0) {
            $res  = "";
            $mask = 0;
        }else {
            $mask = -1;
            $res  = "-";
        }
        self::$PACK->unread(pack('C', $value ^ 0x80));
        $size = $compressed_bytes[$comp_integral];
        if ($size > 0) {
            $value =  self::$PACK->read_int_be_by_size($size) ^ $mask;
            $res .= (string)$value;
        }


        for($i=0;$i<$uncomp_integral;$i++) {
            $value = unpack('N', self::$PACK->read(4))[1] ^ $mask;
            $res .= sprintf('%09d' , $value);
        }

        $res .= ".";
        for($i=0;$i<$uncomp_fractional;$i++) {
            $value = unpack('N', self::$PACK->read(4))[1] ^ $mask;
            $res .= sprintf('%09d' , $value);
        }

        $size = $compressed_bytes[$comp_fractional];
        if ($size > 0) {
            $value = self::$PACK->read_int_be_by_size($size) ^ $mask;

            $res.=sprintf('%0'.$comp_fractional.'d' , $value);
        }
        return number_format($res,$comp_fractional,'.','');
    }



    private static function _getUpdateRows($result, $len) {
        $rows = [];
        while(!self::$PACK->isComplete(self::$PACK_SIZE)) {

            $value['beform'] = self::_read_column_data($result['bitmap1'], $len);
            $value['after'] = self::_read_column_data($result['bitmap2'], $len);
            $rows[] = $value['after'];
        }
        return $rows;
    }

    private static function _getDelRows($result, $len) {
        $rows = [];
        while(!self::$PACK->isComplete(self::$PACK_SIZE)) {
            $rows[] = self::_read_column_data($result['bitmap'], $len);
        }
        return $rows;
    }

    private static function  _getAddRows($result, $len) {
        $rows = [];

        while(!self::$PACK->isComplete(self::$PACK_SIZE)) {
            $rows[] = self::_read_column_data($result['bitmap'], $len);
        }
        return $rows;
    }
}
