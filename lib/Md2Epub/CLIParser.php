<?php

/**
 * Command Line Parser Library
 * 
 * Parses Command Line Arguments and return options and args
 * 
 * Emulates Linux's next_option() function. Some sample options are:
 * <code>
 * $shortOptions = 'vho:a';
 * 
 * $longOptions = array[
 * 		['id', true],
 * 		['name', true],
 * 		['verbose', false, 'v'],
 * 		['output', true, 'o']
 * ];
 * </code>
 *
 * CLI Parser uses some code written by Patrick Fisher (see links)
 * 
 * @link http://www.php.net/manual/en/features.commandline.php#93086
 * @link http://pwfisher.com/nucleus/index.php?itemid=45
 * 
 * @package    Md2Epub
 * @author     Vito Tardia <info@vtardia.com>
 * @copyright  2011 Vito Tardia <http://www.vtardia.com>
 * @license    http://www.opensource.org/licenses/gpl-3.0.html GPL-3.0
 * @version    1.0
 * @since      File available since Release 1.0
 *
 */

namespace Md2Epub;

/*. require_module 'standard'; .*/

/**
 * Main Parser Class
 * 
 * @package    PHPCLIParser
 * @author     Vito Tardia <info@vtardia.com>
 * @version    1.0
 * @since      1.0
 */
class CLIParser
{
	/**
	 * Current program name
	 * @var string | NULL
	 */
	private $program = null;

	/**
	 * Program arguments (eg. filenames)
	 * @var array
	 */
	private $arguments = [];

	/**
	 * Program options (eg. -c -v --name)
	 * @var array
	 */
	private $options = [];

	/**
	 * List of supported short options (-v, -f, ecc)
	 * @var string
	 */
	private $shortOptions = '';


	/**
	 * List of supported long options (eg. --name, --verbose, ecc)
	 * @var array
	 */
	private $longOptions = [];


	/**
	 * Current option being examined
	 * @var int
	 */
	private $optind = 1;
	

	/**
	 * Argument value for the current option
	 * @var string | NULL
	 */
	private $optarg = null;
	

	/**
	 * Copy of global $argv array
	 * @var array
	 */
	private $argv = [];


	/**
	 * Copy of global $argc
	 * @var int
	 */
	private $argc = 0;
	

	/**
	 * Sets the working environment for the program
	 * 
	 * Creates local copies of global $argv and $argc and allows to rewrite the $argv array
	 * 
	 * @param  string  $shortOptions  A string of allowed single-char options. 
	 *                                Parametrized options are followed by the ':' character
	 * 
	 * @param  array   $longOptions   An array of allowed long options which contains: 
	 *                                name (string), parameter need (boolean true/false) and
	 *                                optional short-option equivalent (char)
	 * @param  array   $argv          An array of arguments formatted as the real $argv
	 * 
	 * @return void
	 */
	private function setEnv($shortOptions = '', $longOptions = [], $argv = [])
    {
		if (!empty($argv)) {
			$this->argv = $argv;
			$this->argc = count($argv);
		}
		
		$this->shortOptions = $shortOptions;
		$this->longOptions  = $longOptions;
		
		$this->program = $this->argv[0];
		
	}

	/**
	 * Constructor
	 * 
	 * Sets program's name and copy command line arguments for internal use
	 * 
	 * @param  string  $shortOptions  String containing the short option list (eg cvf:)
	 * @param  array   $longOptions   Array containing the short option list
	 * 
	 * @return void
	 */
	public function __construct($shortOptions = '', $longOptions = [])
    {
		global $argv;
		global $argc;
		
		$this->argv = $argv;
		$this->argc = $argc;
		
		$this->program = $this->argv[0];
		
		$this->setEnv($shortOptions, $longOptions);
	}

    /**
     * Returns the program name
     *
     * @return string
     */
    public function program()
    {
        return $this->program;
    }

    /**
     * Return all the options in an associative array where the key is the option's name
     *
     * For options that act like switches (eg. -v) the array value is true
     * ($options['v'] => true)
     *
     * For options that require a parameter (eg. -o filename) the array value is the
     * parameter value ($options['o'] => "filename")
     *
     * @param  int     $start         Initial index to start with, in order to allow the syntax
     *                                'program command [options] [arguments]'
     *
     * @return array
     */
    public function options( $start = 1)
    {
        // Init index
        $this->optind = (0 == ($start)) ? 1 : intval($start);

        // Loop the arguments until there is no option (-1)
        // At the end of the loop $this->optind points to the first non-option
        // parameter
        do {
            // Query next option
            $nextOption = $this->nextOption();

            // If the option is an option (!== -1) or is valid (not null)
            // set it's value and put it in the options array to return
            if ($nextOption !== null && $nextOption !== -1) {
                if ($this->optarg !== null) {
                    $this->options[$nextOption] = $this->optarg;
                } else {
                    $this->options[$nextOption] = true;
                }
            }

        } while ($nextOption !== -1);

        return $this->options;
    }

    /**
     * Returns program's arguments
     *
     * An argument is everything that is not an option, for example a file path
     *
     * @return array
     */
    public function arguments()
    {
        if ($this->optind < $this->argc) {
            for ($i = $this->optind; $i < $this->argc; $i++) {
                $this->arguments[] = $this->argv[$i];
            }
        }

        return $this->arguments;
    }

    private function validOption($optind, $argc)
    {
        return $optind < $argc;
    }

    private function fetchOption($argv, $optind)
    {
        return $argv[$optind];
    }

    /**
     * Searches for a valid next option
     *
     * If the option is not valid returns NULL, if there is no option returns -1
     *
     * @return mixed | NULL
     */
    private function nextOption()
    {
        // Reset option argument
        $this->optarg = null;

        $shortOptions = $this->shortOptions;
        $longOptions  = $this->longOptions;

        // Check for index validity
        if ($this->validOption($this->optind, $this->argc)) {
            // Get a copy of the current option to examine
            $currentOption = $this->fetchOption($this->argv, $this->optind);

            // The current option is a long option
            if ($this->isLongOption($currentOption)) {
                return $this->nextLongOption($longOptions, $currentOption);
                // The current option is a short option
            } else if ($this->isShortOption($currentOption)) {
                return $this->nextShortOption($shortOptions, $currentOption);
            }
        }

        return -1;
    }

	/********************************* Long option methods *************************************/

    private function getLongOptionKey($option, $positionOfEquals)
    {
        $longOptionKey = '';

        // Find option name
        if ($positionOfEquals === false) {
            // --key value
            $longOptionKey = substr($option, 2);
        } else {
            // --key=value
            $longOptionKey = substr ($option, 2, $positionOfEquals - 2);
        }

        return $longOptionKey;
    }




    private function getLongOptionValue($givenOption, $positionOfEquals)
    {
        // Parsing equal format (--key=value): parameter value is the string after '='
        return substr($givenOption, $positionOfEquals + 1);
    }

    public function updateOptionArgAndIndex($opt, $positionOfEquals, $givenOption)
    {
        // If option should have a parameter
        if (isset($opt[1]) && true === $opt[1]) {
            if ($positionOfEquals !== false) {
                $this->optarg = $this->getLongOptionValue($givenOption, $positionOfEquals);

                // Index is updated by 1 step only
                $this->optind = $this->optind + 1;
            } else {
                // Parameter should be in the command line in the next position

                // Test if arg is a real arg or another option (contains - or --)
                if ($this->optind < $this->argc -1) {
                    $optarg = $this->fetchOption($this->argv, $this->optind + 1);

                    if (!$this->isLongOption($optarg) && !$this->isShortOption($optarg)) {
                        // Option value is ok, set it and update index by 2 steps
                        $this->optarg = $optarg;
                        $this->optind = $this->optind + 2;
                    } else {
                        // Option value missing, set it to false and update index by 1 step only
                        $this->optarg = false;
                        $this->optind = $this->optind + 1;
                    }
                } else {
                    // Option value missing, set it to false and update index by 1 step only
                    $this->optarg = false;
                    $this->optind = $this->optind + 1;
                }
            }
        } else {
            $this->optarg = true;
            $this->optind = $this->optind + 1;
        }
    }

    private function getLongOption($longOptions, $longOptionKey, $positionOfEquals, $givenOption)
    {
        $option = null;

        // Search if the option is in the list of valid long options
        foreach ($longOptions as $opt) {
            // Transform in array, this allow string-only declarations in options array
            if (!is_array($opt)) {
                $opt = array($opt);
            }

            // Match not found, go to next
            if ((string)$opt[0] !== $longOptionKey) {
                continue;
            } else {
                // Match found, set return option name
                $option = $longOptionKey;

                // If 1-char equiv is present, it overrides long name
                if (isset($opt[2]) && strlen((string)$opt[2]) == 1) {
                    $option = $opt[2];
                }

                $this->updateOptionArgAndIndex($opt, $positionOfEquals, $givenOption);
            }
        }

        return $option;
    }

    private function nextLongOption($longOptions, $givenOption)
    {
        // If our program does not accept long options
        // ignore current keyword
        if (empty($longOptions)) {
            $this->optind++;

            return null;
        }

        // Parse the equal syntax
        // We allow for:
        // --key=value
        // --key value
        $positionOfEquals = strpos($givenOption, '=');
        $longOptionKey = $this->getLongOptionKey($givenOption, $positionOfEquals);

        return $this->getLongOption($longOptions, $longOptionKey, $positionOfEquals, $givenOption);
    }

    private function isLongOption($option)
    {
        return (substr($option, 0, 2) === '--');
    }

	/*********************************Short option methods *************************************/

    private function processGroupedShortOptions($shortOptions, $key)
    {
        // We are parsing an option group
        // We parse every single character, remove it from the current argument
        // and wd DO NOT update index counter, unless we are parsing the last option
        $chars = str_split($key);
        foreach ($chars as $char) {
            // Test if is a valid option
            $cpos = strpos($shortOptions, $char);
            if ($cpos !== false) {
                // Is Valid option: set return value
                $option = $char;
                // Check if option accept a parameter
                if (isset($shortOptions[$cpos+1]) && $shortOptions[$cpos+1] === ':') {
                    // Ok, current option accept a parameter, start parsing
                    // Check n.1: if an option accepts a parameter, to be valid must be the last
                    // in an option-group, for example:
                    // YES: tar -cvzf filename.tar.gz
                    // NO: tar -cvfz filename.tar.gz
                    if (strpos($key, $char) < (strlen($key)-1)) {
                        // Current option is not the last, so the parameter is invalid
                        // return option with false as argument
                        $this->optarg = false;

                        // Remove current option from $this->argv[$this->optind]
                        // so it's not counted on next loop
                        $this->argv[$this->optind] = str_replace($char, '', $this->argv[$this->optind]);

                        return $option;
                    }

                    // Our option is the last, so check for a valid parameter
                    if ($this->optind < $this->argc -1) {
                        // Get next argument from our copy of $argv
                        $optarg = (string) $this->argv[$this->optind + 1];

                        // If the argument is not an option (should not begin with - or --)
                        if (!$this->isLongOption($optarg) && !$this->isShortOption($optarg)) {
                            // Option value is ok, set it and update index by 2 steps
                            $this->optarg = $optarg;
                            $this->optind = $this->optind + 2;

                            return $option;
                        } else {
                            // Option value missing, set it to false and update index by 1 step only
                            $this->optarg = false;
                            $this->optind = $this->optind + 1;

                            return $option;
                        }
                    } else {
                        // Option value missing, set it to false and update index by 1 step only
                        $this->optarg = false;
                        $this->optind = $this->optind + 1;

                        return $option;
                    }
                } else {
                    // Current option do not accept parameters
                    $this->optarg = true;

                    // Remove current option from $this->argv[$this->optind]
                    // so it's not counted on next loop
                    $this->argv[$this->optind] = str_replace($char, '', $this->argv[$this->optind]);

                    // Return the option value
                    // Option index is updated (by 1) only if this is the last option
                    if (strpos($key, $char) == (strlen($key)-1)) {
                        $this->optind = $this->optind + 1;
                    }

                    return $option;
                }
            }
        }

        // If we reach here the chance is we didn't find any allowed option
        // so we return null
        if (strpos($key, $char) == (strlen($key)-1)) {
            $this->optind = $this->optind + 1;
        }

        return null;
    }

    private function processSingleShortOption($shortOptions, $key)
    {
        // Check if option is allowed
        $cpos = strpos($shortOptions, $key);
        if ($cpos !== false) {
            // Ok, our option is supported
            $option = $key;
            // Check if option allows parameters
            if (isset($shortOptions[$cpos+1]) && $shortOptions[$cpos+1] === ':') {
                // Ok, parse parameter
                if ($this->optind < $this->argc -1) {
                    // Get next arg from our copy of $argv
                    $optarg = $this->argv[$this->optind + 1];

                    // If arg is not an option (should not begin with - or --)
                    if (!(substr($optarg, 0, 2) === '--') && !(substr($optarg, 0, 1) === '-')) {
                        // Option value is ok, set it and update index by 2 steps
                        $this->optarg = $optarg;
                        $this->optind = $this->optind + 2;
                    } else {
                        // Option value missing, set it to false and update index by 1 step only
                        $this->optarg = false;
                        $this->optind = $this->optind + 1;
                    }
                } else {
                    // Option value missing, set it to false and update index by 1 step only
                    $this->optarg = false;
                    $this->optind = $this->optind + 1;
                }
            } else {
                // No parameter
                $this->optarg = true;
                $this->optind = $this->optind + 1;
            }

            // In any case we now have a supported option
            return $option;
        } else {
            // Invalid option, go to next
            $this->optind = $this->optind + 1;

            return null;
        }
    }

    private function nextShortOption($shortOptions, $givenOption)
    {
        // If our program does not accept short options
        // ignore current keyword
        if (empty($shortOptions)) {
            $this->optind++;

            return null;
        }

        // We do not accept single letter options with '=' (format -o=value)
        if (is_numeric(strpos($givenOption, '='))) {
            $this->optind++;

            return null;
        } else {
            // We must accept grouped option (eg. -cvd)
            $key = substr($givenOption, 1);

            if (strlen($key) > 1) {
                return $this->processGroupedShortOptions($shortOptions, $key);
                // Deal with a single-char option, no option group
            } else if (strlen($key) == 1) {
                return $this->processSingleShortOption($shortOptions, $key);
            } else {
                // Invalid option, go to next
                $this->optind = $this->optind + 1;

                return null;
            }
        }
    }

    private function isShortOption($option)
    {
        return (!$this->isLongOption($option)) && (substr($option, 0, 1) === '-');
    }
}
