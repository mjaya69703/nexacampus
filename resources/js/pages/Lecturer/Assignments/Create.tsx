// Halaman buat tugas baru untuk kelas dosen.
import { ShellProps } from '../../../components/Shared/AdminShell';
import AssignmentForm, { AssignmentDefaults } from './AssignmentForm';

type OfferingInfo = {
    course: string; label: string; academicYear: string; studyProgram: string; enrolledCount: number;
};

type Props = {
    shell: ShellProps;
    offeringId: number;
    offeringInfo: OfferingInfo;
    defaults: AssignmentDefaults;
    urls: { index: string; store: string; show: string };
};

export default function AssignmentsCreate({ shell, offeringInfo, defaults, urls }: Props) {
    return (
        <AssignmentForm
            shell={shell}
            pageTitle="Buat Tugas"
            heroDescription={`${offeringInfo.course} · ${offeringInfo.label}`}
            defaults={defaults}
            existingFiles={[]}
            offeringInfo={offeringInfo}
            urls={{ index: urls.index, submit: urls.store, show: urls.show }}
            submitMethod="post"
        />
    );
}
