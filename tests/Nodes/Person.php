<?php
/**
 * Copyright (C) 2012 Louis-Philippe Huberdeau
 *
 * Permission is hereby granted, free of charge, to any person obtaining a 
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace Nodes;
use OGM\Neo4j\Mapping\Annotations as OGM;

/**
 * @OGM\Node
 */
class Person
{
    /**
     * @OGM\Id
     */
    protected $id;

    /**
     * @OGM\String
     * @OGM\Index
     */
    protected $firstName;

    /**
     * @OGM\String
     * @OGM\Index
     */
    protected $lastName;

    /**
     * @OGM\HasMany(targetNode="Person", relationship="Friend")
     */
    protected $friends;

    function __construct()
    {
        $this->friends = new \Doctrine\Common\Collections\ArrayCollection;
    }

    function getId()
    {
        return $this->id;
    }

    function setId($id)
    {
        $this->id = $id;
		return $this;
    }

    function getFirstName()
    {
        return $this->firstName;
    }

    function setFirstName($firstName)
    {
        $this->firstName = $firstName;
		return $this;
    }

    function getLastName()
    {
        return $this->lastName;
    }

    function setLastName($lastName)
    {
        $this->lastName = $lastName;
		return $this;
    }

    function getFriends()
    {
        return $this->friends;
    }

    function addFriend(Person $friend)
    {
        $this->friends->add($friend);
		return $this;
    }
}

