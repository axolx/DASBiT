<?php
/**
 * DASBiT
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @version $Id$
 */

/**
 * Request object which handles privmsg
 */
class DASBiT_Irc_Request
{
    /**
     * The full ident of the user
     *
     * @var string
     */
    protected $_ident;
    
    /**
     * The nickname of the user
     *
     * @var string
     */
    protected $_nickname;
    
    /**
     * The username of the user
     *
     * @var strng
     */
    protected $_username;
    
    /**
     * The hostname of the user
     *
     * @var string
     */
    protected $_hostname;
    
    /**
     * The source (channel or nickname) from which the request comes 
     *
     * @var string
     */
    protected $_source;
    
    /**
     * The message itself
     *
     * @var string
     */
    protected $_message;
    
    /**
     * Words contained in the message
     *
     * @var array
     */
    protected $_words;
    
    /**
     * The command which was sent with the PRIVMSG
     *
     * @var unknown_type
     */
    protected $_command;
    
    /**
     * Create the request object
     *
     * @param string $line            The PRIVMSG line from the server
     * @param string $currentNickname Current nickname of the bot
     */
    public function __construct($line, $currentNickname)
    {
        preg_match("#^:(([^!]+)!([^@]+)@([^ ]+)) PRIVMSG ([^ ]+) :(([^ ]+).*)$#", $line, $match);
        
        list(,
             $this->_ident,
             $this->_nickname,
             $this->_username,
             $this->_hostname,
             ,
             $this->_message,
             $this->_command) = $match;
        
        $this->_words = explode(' ', $this->_message);

        if (strcasecmp($match[5], $currentNickname) === 0) {
            $this->_source = $match[2];
        } else {
            $this->_source = $match[5];
        }
    }
    
    /**
     * Get the ident of the user
     *
     * @return string
     */
    public function getIdent()
    {
        return $this->_ident;
    }
    
    /**
     * Get the nickname of the user
     *
     * @return string
     */
    public function getNickname()
    {
        return $this->_nickname;
    }
    
    /**
     * Get the username of the user
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }
    
    /**
     * Get the hostname of the user
     *
     * @return string
     */
    public function getHostname()
    {
        return $this->_hostname;
    }
    
    /**
     * Get the source where the message comes from
     * 
     * This can either be a channel or a username
     *
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }
    
    /**
     * Get the content of the request
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->_message;
    }
    
    /**
     * Get the content of the request splited in words
     *
     * @return string
     */
    public function getWords()
    {
        return $this->_words;
    }
    
    
    /**
     * Get the command sent with the request
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->_command;
    }
}