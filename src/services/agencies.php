<?php
class Agencies extends API_configuration {
    public function create(
        string $number,
        string $name
    ) {
        $sql = 'INSERT INTO `agencies`(`number`, `name`) VALUES ("' . $number . '","' . $name . '")';
        $agency_created = $this->db_create($sql);
        if ($agency_created) {
            $slug = $this->slugify($agency_created . '-' . $name);
            $sql = 'UPDATE `agencies` SET `slug`="' . $slug . '" WHERE `id`=' . $agency_created;
            $this->db_update($sql);

            return $this->read_by_slug($slug);
        } else {
            return false;
        }
    }

    public function read() {
        $sql = 'SELECT `id`, `number`, `name`, `slug` FROM `agencies` ORDER BY `name`';
        $agencies = $this->db_read($sql);
        if ($agencies) {
            $response = [];
            while ($agency = $this->db_object($agencies)) {
                $response[] = [
                    'id' => (int) $agency->id,
                    'number' => $agency->number,
                    'name' => mb_convert_case($agency->name, MB_CASE_TITLE, 'UTF-8'),
                    'slug' => $agency->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT `id`, `number`, `name`, `status` FROM `agencies` WHERE `slug`="' . $slug . '"';
        $agency = $this->db_read($sql);
        if ($agency) {
            $agency = $this->db_object($agency);
            $agency->id = (int) $agency->id;
            $agency->status = ($agency->status == "true" ? true : false);
            return $agency;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `agencies` WHERE `id`=' . $id;
        $agency = $this->db_read($sql);
        if ($agency) {
            $agency = $this->db_object($agency);
            $agency->id = (int) $agency->id;
            $agency->status = ($agency->status == "true" ? true : false);
            return $agency;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $number,
        string $name,
        bool $status
    ) {
        $old_agency = $this->read_by_id($id);
        $sql = '
        UPDATE `agencies` SET
            `number`="' . $number . '",
            `name`="' . $name . '",
            `status`="' . $status . '",
            `slug`="' . $this->slugify($id . '-' . $name) . '"
        WHERE `id`=' . $id;
        $agency_updated = $this->db_update($sql);
        if ($agency_updated) {
            return [
                'old' => $old_agency,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug) {
        $old_agency = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `agencies` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_agency;
        } else {
            return false;
        }
    }
}