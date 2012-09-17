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
use Doctrine\Common\Collections\ArrayCollection;
use OGM\Neo4j\Mapping\Annotations as OGM;

/**
 * @OGM\Node
 */
class Cinema
{
    /**
     * @OGM\Id
     */
    protected $id;

    /**
     * @OGM\String
     */
    protected $name;

    /**
     * @OGM\HasMany(targetNode="Movie", relationship="PresentedMovie")
     */
    protected $presentedMovies;

    /**
     * @OGM\HasMany(targetNode="Movie", relationship="RejectedMovie")
     */
    protected $rejectedMovies;

    function __construct()
    {
        $this->presentedMovies = new ArrayCollection;
        $this->rejectedMovies = new ArrayCollection;
    }

    function getId()
    {
        return $this->id;
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function getName()
    {
        return $this->name;
    }

    function setName($name)
    {
        $this->name = $name;
    }

    function getPresentedMovies()
    {
        return $this->presentedMovies;
    }

    function addPresentedMovie($movie)
    {
        $this->presentedMovies->add($movie);
    }

    function setPresentedMovies(ArrayCollection $movies)
    {
        $this->presentedMovies = $movies;
    }

    function getRejectedMovies()
    {
        return $this->rejectedMovies;
    }
}

