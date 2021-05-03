import unittest
import io
import sys
import warnings

sys.path.insert(0, '..')
target = __import__("import_doh_client_certs")


class TestMain(unittest.TestCase):
    """
    TestMain Class
    """

    def setUp(self):
        """
        Do setup
        """
        # ignore the unclosed file warnings until
        # I can figure out why they're showing up.
        warnings.filterwarnings(
            action="ignore",
            message="unclosed",
            category=ResourceWarning
        )

    def test_one_enabled_of_two(self):
        """
        Only one doh certificate enabled of two.
        """
        config = 'fixtures/doh_client_certs/one_enabled_of_two.xml'
        output_dir = "output/doh_client_certs"

        target.main(config, output_dir)
        self.assertListEqual(
            list(
                io.open(
                    output_dir
                    + "/57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert.pem"
                )
            ),
            list(
                io.open(
                    "references/doh_client_certs"
                    "/57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert.pem"
                )
            )
        )


if __name__ == '__main__':
    unittest.main()
