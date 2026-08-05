# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

import pathlib
import unittest


BIND_ROOT = pathlib.Path(__file__).resolve().parents[1]
OPNSENSE_ROOT = BIND_ROOT / 'src/opnsense'
CONTROLLER = OPNSENSE_ROOT / 'mvc/app/controllers/OPNsense/Bind/Api/DhcprecordController.php'
RECORDS_VIEW = OPNSENSE_ROOT / 'mvc/app/views/OPNsense/Bind/records.volt'


class TestDhcpRecordsDomainColumn(unittest.TestCase):
    def test_active_records_expose_and_display_the_watcher_domain(self):
        controller = CONTROLLER.read_text()
        view = RECORDS_VIEW.read_text()

        self.assertIn("'domain' => $lease['suffix'] ?? ''", controller)
        self.assertIn('data-column-id="domain"', view)
        self.assertIn("lang._('Domain')", view)

    def test_legacy_state_is_not_presented_as_an_active_record(self):
        controller = CONTROLLER.read_text()

        self.assertIn("empty($lease['suffix'])", controller)
