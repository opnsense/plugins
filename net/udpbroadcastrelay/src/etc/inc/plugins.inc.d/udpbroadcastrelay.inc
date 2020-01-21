<?php

/*

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function udpbroadcastrelay_enabled()
{
    $model = new \OPNsense\UDPBroadcastRelay\UDPBroadcastRelay();

    foreach ($model->udpbroadcastrelay->iterateItems() as $server) {
        if ($server->enabled == '1') {
            return true;
        }
    }

    return false;
}

function udpbroadcastrelay_firewall($fw)
{
    if (!udpbroadcastrelay_enabled()) {
        return;
    }
}

function udpbroadcastrelay_services()
{
    $services = array();

    if (!udpbroadcastrelay_enabled()) {
        return $services;
    }
    
    $model = new \OPNsense\UDPBroadcastRelay\UDPBroadcastRelay();

    foreach ($model->udpbroadcastrelay->iterateItems() as $server) {
     
        if ($server->enabled == '0'){
            continue;
        }

        $services[] = array(
            
            'description' => $server->description,
            'id' =>  $server->InstanceID,
            'pidfile' => "/var/run/udpbroadcastrelay_{$server->InstanceID}.pid",
            'configd' => array(
                'restart' => array('udpbroadcastrelay restart '.$server->InstanceID),
                'start' => array('udpbroadcastrelay start '.$server->InstanceID),
                'stop' => array('udpbroadcastrelay stop '.$server->InstanceID),
               
            ),
            
            'name' => 'udpbroadcastrelay',
        );

    }
    
    return $services;
}
