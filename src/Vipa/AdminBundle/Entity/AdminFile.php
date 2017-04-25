<?php

namespace Vipa\AdminBundle\Entity;

use Prezent\Doctrine\Translatable\Annotation as Prezent;
use Vipa\CoreBundle\Annotation\Display;
use APY\DataGridBundle\Grid\Mapping as GRID;
use Vipa\CoreBundle\Entity\DisplayTrait;
use Vipa\CoreBundle\Entity\TagsTrait;

/**
 * AdminFile
 * @GRID\Source(columns="id, name, description, path")
 */
class AdminFile
{
    use DisplayTrait;
    use TagsTrait;

    /**
     * @var int
     * @GRID\Column(title="id")
     */
    private $id;

    /**
     * @var string
     * @GRID\Column(title="name")
     */
    private $name;

    /**
     * @var string
     * @GRID\Column(title="description")
     */
    private $description;

    /**
     * @var string
     * @GRID\Column(title="path")
     * @Display\File(path="files")
     */
    private $path;

    /**
     * @var int
     */
    private $size;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->getName();
    }
}

