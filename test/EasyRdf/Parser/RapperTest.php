<?php
namespace EasyRdf\Parser;

/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2013 Nicholas J Humfrey.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. The name of the author 'Nicholas J Humfrey" may be used to endorse or
 *    promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2013 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 */

use EasyRdf\Graph;
use EasyRdf\TestCase;

require_once dirname(dirname(__DIR__)).
             DIRECTORY_SEPARATOR.'TestHelper.php';

class RapperTest extends TestCase
{
    /** @var Rapper */
    protected $parser = null;
    /** @var Graph */
    protected $graph = null;
    protected $rdf_data = null;

    public function setUp()
    {
        exec('rapper --version 2>/dev/null', $output, $retval);
        if ($retval == 0) {
            $this->parser = new Rapper();
            $this->graph = new Graph();
            $this->rdf_data = readFixture('foaf.rdf');
        } else {
            $this->markTestSkipped(
                "The rapper command is not available on this system."
            );
        }
    }

    public function testRapperNotFound()
    {
        $this->setExpectedException(
            'EasyRdf\Exception',
            "Failed to execute the command 'random_command_that_doesnt_exist'"
        );
        new Rapper('random_command_that_doesnt_exist');
    }

    public function testRapperTooOld()
    {
        $this->setExpectedException(
            'EasyRdf\Exception',
            "Version 1.4.17 or higher of rapper is required."
        );
        new Rapper('echo 1.0.0');
    }

    public function testParseRdfXml()
    {
        $count = $this->parser->parse(
            $this->graph,
            $this->rdf_data,
            'rdfxml',
            'http://www.example.com/joe/foaf.rdf'
        );
        $this->assertSame(14, $count);

        $joe = $this->graph->resource('http://www.example.com/joe#me');
        $this->assertNotNull($joe);
        $this->assertClass('EasyRdf\Resource', $joe);
        $this->assertSame('http://www.example.com/joe#me', $joe->getUri());

        $name = $joe->get('foaf:name');
        $this->assertNotNull($name);
        $this->assertClass('EasyRdf\Literal', $name);
        $this->assertStringEquals('Joe Bloggs', $name);
        $this->assertSame('en', $name->getLang());
        $this->assertSame(null, $name->getDatatype());

        $foaf = $this->graph->resource('http://www.example.com/joe/foaf.rdf');
        $this->assertNotNull($foaf);
        $this->assertStringEquals("Joe Bloggs' FOAF File", $foaf->label());
    }

    public function testParseEmpty()
    {
        $count = $this->parser->parse(
            $this->graph,
            readFixture('empty.rdf'),
            'rdfxml',
            'http://www.example.com/empty.rdf'
        );

        // Should be empty but no exception thrown
        $this->assertSame(0, $count);
        $this->assertSame(0, $this->graph->countTriples());
    }

    public function testParseXMLLiteral()
    {
        $count = $this->parser->parse(
            $this->graph,
            readFixture('xml_literal.rdf'),
            'rdfxml',
            'http://www.example.com/'
        );
        $this->assertSame(2, $count);

        $doc = $this->graph->resource('http://www.example.com/');
        $this->assertSame('foaf:Document', $doc->type());
        $description = $doc->get('dc:description');
        $this->assertSame('rdf:XMLLiteral', $description->getDataType());
        $this->assertSame(
            "\n      <p>Here is a block of <em>HTML text</em></p>\n    ",
            $description->getValue()
        );
    }

    public function testParseUnsupportedFormat()
    {
        $this->setExpectedException(
            'EasyRdf\Exception',
            'Error while executing command rapper'
        );
        $this->parser->parse(
            $this->graph,
            $this->rdf_data,
            'unsupportedformat',
            'http://test/' // we can't use null here, otherwise it would raise a baseUri related error
        );
    }

    /**
     * Tests faulty behavior of issue #8.
     *
     * If baseUri parameter is empty, rapper command raises an error.
     *
     * @see https://github.com/sweetyrdf/easyrdf/issues/8
     */
    public function testParseIssue8()
    {
        $this->setExpectedException(
            Exception::class,
            'rapper command requires $baseUri set, when reading from standard input'
        );

        $this->parser->parse(new Graph(), readFixture('xml_literal.rdf'), 'rdfxml', null);
    }
}
