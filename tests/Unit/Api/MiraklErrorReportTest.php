<?php

use PHPUnit\Framework\TestCase;

class MiraklErrorReportTest extends TestCase
{
    public function test_empty_input_returns_empty_array()
    {
        $this->assertSame([], MiraklErrorReport::parse(''));
        $this->assertSame([], MiraklErrorReport::parse("   \n  "));
        $this->assertSame([], MiraklErrorReport::parse(null));
    }

    public function test_parses_semicolon_delimited_csv()
    {
        $csv = "sku;error_code;error_message\n"
             . "A1;OF-01;Missing EAN\n"
             . "B2;OF-23;Invalid price\n";

        $rows = MiraklErrorReport::parse($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('A1', $rows[0]['sku']);
        $this->assertSame('OF-01', $rows[0]['error_code']);
        $this->assertSame('Missing EAN', $rows[0]['error_message']);
        $this->assertSame('B2', $rows[1]['sku']);
    }

    public function test_autodetects_comma_delimiter_when_semicolon_absent()
    {
        $csv = "sku,error_code,error_message\nA1,OF-01,Missing EAN\n";

        $rows = MiraklErrorReport::parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('A1', $rows[0]['sku']);
        $this->assertSame('OF-01', $rows[0]['error_code']);
    }

    public function test_handles_quoted_fields_with_delimiter_inside()
    {
        $csv = "sku;message\n"
             . "A1;\"Error: price; too; high\"\n";

        $rows = MiraklErrorReport::parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('Error: price; too; high', $rows[0]['message']);
    }

    public function test_handles_crlf_line_endings()
    {
        $csv = "sku;error\r\nA1;X\r\nB2;Y\r\n";

        $rows = MiraklErrorReport::parse($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('A1', $rows[0]['sku']);
        $this->assertSame('B2', $rows[1]['sku']);
    }

    public function test_missing_trailing_columns_produce_empty_strings()
    {
        $csv = "sku;error_code;error_message\nA1;OF-01;\n";

        $rows = MiraklErrorReport::parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['error_message']);
    }

    public function test_count_errors()
    {
        $csv = "sku;error\nA;X\nB;Y\nC;Z\n";
        $this->assertSame(3, MiraklErrorReport::countErrors($csv));
        $this->assertSame(0, MiraklErrorReport::countErrors(''));
    }
}
